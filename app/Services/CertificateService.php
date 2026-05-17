<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateSetting;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    private const TEMPLATE_VERSION = 'v4';
    private const CERTIFICATE_DISPLAY_TIMEZONE = 'Asia/Jakarta';

    public function __construct(
        protected AssignmentService $assignmentService
    ) {}

    public function getUserCertificates(User $user)
    {
        $certificates = Certificate::where('user_id', $user->id)
            ->with('course')
            ->latest()
            ->paginate(15);

        $certificates->getCollection()->transform(
            fn (Certificate $certificate) => $this->prepareCertificateMetadata($certificate)
        );

        return $certificates;
    }

    public function getCertificateByEnrollment(int $enrollmentId, User $user): ?Certificate
    {
        $enrollment = $this->findOwnedEnrollment($enrollmentId, $user);
        $certificate = $enrollment->certificate
            ?? Certificate::where('enrollment_id', $enrollment->id)->first();

        if (! $certificate) {
            return null;
        }

        return $this->prepareCertificateMetadata($certificate);
    }

    public function generateCertificate(int $enrollmentId, User $user): Certificate
    {
        $enrollment = $this->findOwnedEnrollment($enrollmentId, $user);

        return $this->ensureGeneratedForEnrollment($enrollment, true);
    }

    public function getOwnedCertificate(int $certificateId, User $user): Certificate
    {
        $certificate = Certificate::where('user_id', $user->id)
            ->with(['course', 'user'])
            ->findOrFail($certificateId);

        return $this->prepareCertificateMetadata($certificate);
    }

    public function getManagedCertificateForOffering(int $offeringId, int $certificateId, User $actor): Certificate
    {
        $certificate = Certificate::query()
            ->with(['course', 'user', 'enrollment.courseOffering.course'])
            ->where('id', $certificateId)
            ->whereHas('enrollment', function ($query) use ($offeringId) {
                $query->where('course_offering_id', $offeringId);
            })
            ->firstOrFail();

        $this->assertCanManageOffering($certificate->enrollment?->courseOffering, $actor);

        return $this->prepareCertificateMetadata($certificate);
    }

    public function generateCertificateForManagedEnrollment(
        int $offeringId,
        int $enrollmentId,
        User $actor
    ): Certificate {
        $enrollment = Enrollment::query()
            ->with(['courseOffering.course', 'user', 'certificate'])
            ->where('id', $enrollmentId)
            ->where('course_offering_id', $offeringId)
            ->firstOrFail();

        $this->assertCanManageOffering($enrollment->courseOffering, $actor);

        return $this->ensureGeneratedForEnrollment($enrollment, true, true);
    }

    public function getDownloadFilename(Certificate $certificate): string
    {
        $studentSlug = Str::slug($certificate->user?->fullname ?? 'student') ?: 'student';
        $courseSlug = Str::slug($certificate->course?->title ?? 'course') ?: 'course';

        return $studentSlug . '-' . $courseSlug . '-certificate.pdf';
    }

    public function renderCertificatePdf(Certificate $certificate): string
    {
        $certificate = $this->prepareCertificateMetadata($certificate);
        $snapshot = $this->snapshotForRendering($certificate);
        $snapshot['is_print_preview'] = false;
        $snapshot['trigger_print'] = false;

        $pdf = Pdf::setOption([
            'isPhpEnabled' => false,
            'isRemoteEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ])->loadView('certificates.default', $snapshot);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->output();
    }

    public function renderCertificatePrintPreview(Certificate $certificate): string
    {
        $certificate = $this->prepareCertificateMetadata($certificate);
        $snapshot = $this->snapshotForRendering($certificate);
        $snapshot['is_print_preview'] = true;
        $snapshot['trigger_print'] = true;

        return view('certificates.default', $snapshot)->render();
    }

    public function ensureGeneratedForEnrollment(
        Enrollment $enrollment,
        bool $throwWhenIneligible = false,
        bool $refreshSnapshot = false
    ): ?Certificate {
        $enrollment->loadMissing(['courseOffering.course', 'user', 'certificate']);

        if ($enrollment->status !== 'completed') {
            if ($throwWhenIneligible) {
                throw ValidationException::withMessages([
                    'enrollment_id' => ['You must complete the course to get a certificate.'],
                ]);
            }

            return null;
        }

        if (! $this->assignmentService->isCompletionRequirementMet($enrollment)) {
            if ($throwWhenIneligible) {
                throw ValidationException::withMessages([
                    'assignment' => ['Required assignment approval is not complete yet.'],
                ]);
            }

            return null;
        }

        $courseId = $enrollment->courseOffering?->course_id;
        if (! $courseId) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Enrollment is missing a valid course offering reference.'],
            ]);
        }

        $settings = $this->getCertificateSettings();
        $issuedAt = now();
        $expiredAt = $this->calculateExpiredAt($issuedAt, $settings);
        $certificate = $enrollment->certificate
            ?? Certificate::where('enrollment_id', $enrollment->id)->first();

        if (! $certificate) {
            $certificateNumber = $this->generateCertificateNumber($settings);
            $verificationCode = $this->generateVerificationCode();
            $certificate = Certificate::create([
                'user_id' => $enrollment->user_id,
                'course_id' => $courseId,
                'enrollment_id' => $enrollment->id,
                'certificate_number' => $certificateNumber,
                'certificate_url' => $this->downloadUrlPlaceholder(),
                'status' => 'active',
                'template_version' => self::TEMPLATE_VERSION,
                'verification_code' => $verificationCode,
                'snapshot_data' => $this->buildSnapshot($enrollment, $settings, $certificateNumber, $verificationCode, $issuedAt, $expiredAt),
                'issued_at' => $issuedAt,
                'expired_at' => $expiredAt,
            ]);
        }

        $certificate->loadMissing(['course', 'user']);

        $payload = [];
        if ((int) $certificate->course_id !== $courseId) {
            $payload['course_id'] = $courseId;
        }
        if ((int) $certificate->user_id !== (int) $enrollment->user_id) {
            $payload['user_id'] = $enrollment->user_id;
        }
        if (! $certificate->issued_at) {
            $payload['issued_at'] = $issuedAt;
        }
        if (! $certificate->verification_code) {
            $payload['verification_code'] = $this->generateVerificationCode();
        }
        if (! $certificate->status) {
            $payload['status'] = 'active';
        }
        if (! $certificate->certificate_url || $certificate->certificate_url === $this->downloadUrlPlaceholder()) {
            $payload['certificate_url'] = url('/api/certificates/' . $certificate->id . '/download');
        }

        $needsSnapshot = $refreshSnapshot
            || ! is_array($certificate->snapshot_data)
            || ! $certificate->verification_code
            || $certificate->template_version !== self::TEMPLATE_VERSION;

        if ($needsSnapshot) {
            $payload['template_version'] = self::TEMPLATE_VERSION;
            $payload['snapshot_data'] = $this->buildSnapshot(
                $enrollment,
                $settings,
                $certificate->certificate_number,
                $payload['verification_code'] ?? $certificate->verification_code ?? $this->generateVerificationCode(),
                $certificate->issued_at ?? $issuedAt,
                $certificate->expired_at ?? $expiredAt
            );
        }

        if ($payload !== []) {
            $certificate->update($payload);
            $certificate = $certificate->fresh(['course', 'user']);
        }

        return $this->prepareCertificateMetadata($certificate);
    }

    public function getCertificateSettings(): CertificateSetting
    {
        return CertificateSetting::query()->firstOrCreate([], [
            'organization_name' => 'OpenLearning LMS',
            'certificate_title' => 'Certificate of Completion',
            'certificate_prefix' => 'CERT',
            'footer_note' => 'This certificate is generated automatically by the system.',
        ]);
    }

    public function updateCertificateSettings(array $payload): CertificateSetting
    {
        $settings = $this->getCertificateSettings();
        $settings->update($payload);

        return $settings->fresh();
    }

    public function uploadCertificateAsset(UploadedFile $file, string $type): string
    {
        $directory = 'certificate-assets';
        $filename = $type . '-' . now()->format('YmdHis') . '-' . Str::random(8) . '.' . $file->extension();
        $path = $file->storeAs($directory, $filename, 'public');

        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['Certificate asset upload failed.'],
            ]);
        }

        return '/storage/' . ltrim($path, '/');
    }

    private function findOwnedEnrollment(int $enrollmentId, User $user): Enrollment
    {
        return Enrollment::where('user_id', $user->id)
            ->with(['courseOffering.course', 'user', 'certificate'])
            ->findOrFail($enrollmentId);
    }

    private function prepareCertificateMetadata(Certificate $certificate): Certificate
    {
        $certificate->loadMissing(['course', 'user', 'enrollment.courseOffering.course', 'enrollment.user']);

        $payload = [];
        $downloadUrl = url('/api/certificates/' . $certificate->id . '/download');
        if ($certificate->certificate_url !== $downloadUrl) {
            $payload['certificate_url'] = $downloadUrl;
        }
        if (! $certificate->verification_code) {
            $payload['verification_code'] = $this->generateVerificationCode();
        }
        if (! $certificate->status) {
            $payload['status'] = 'active';
        }
        if (! is_array($certificate->snapshot_data) && $certificate->enrollment) {
            $issuedAt = $certificate->issued_at instanceof Carbon
                ? $certificate->issued_at
                : Carbon::parse($certificate->issued_at ?? now());
            $settings = $this->getCertificateSettings();
            $payload['template_version'] = self::TEMPLATE_VERSION;
            $payload['snapshot_data'] = $this->buildSnapshot(
                $certificate->enrollment,
                $settings,
                $certificate->certificate_number,
                $payload['verification_code'] ?? $certificate->verification_code ?? $this->generateVerificationCode(),
                $issuedAt,
                $certificate->expired_at
            );
        }

        if ($payload !== []) {
            $certificate->update($payload);
            $certificate = $certificate->fresh(['course', 'user', 'enrollment.courseOffering.course', 'enrollment.user']);
        }

        return $certificate;
    }

    private function snapshotForRendering(Certificate $certificate): array
    {
        $snapshot = is_array($certificate->snapshot_data) ? $certificate->snapshot_data : [];
        $issuedAt = $certificate->issued_at instanceof Carbon
            ? $certificate->issued_at
            : Carbon::parse($certificate->issued_at ?? now());
        $expiredAt = $certificate->expired_at instanceof Carbon
            ? $certificate->expired_at
            : ($certificate->expired_at ? Carbon::parse($certificate->expired_at) : null);

        return [
            'organization_name' => $snapshot['organization_name'] ?? 'OpenLearning LMS',
            'certificate_title' => $snapshot['certificate_title'] ?? 'Certificate of Completion',
            'student_name' => $snapshot['student_name'] ?? $certificate->user?->fullname ?? $certificate->user?->email ?? 'Student',
            'course_title' => $snapshot['course_title'] ?? $certificate->course?->title ?? 'Course',
            'certificate_number' => $snapshot['certificate_number'] ?? $certificate->certificate_number,
            'issue_date' => $this->formatCertificateDate($issuedAt),
            'issued_at' => $this->formatCertificateDateTime($issuedAt),
            'expired_at' => $this->formatCertificateDateTime($expiredAt),
            'verification_code' => $snapshot['verification_code'] ?? $certificate->verification_code,
            'signatory_name' => $snapshot['signatory_name'] ?? null,
            'signatory_title' => $snapshot['signatory_title'] ?? null,
            'signature_image' => $this->resolveTemplateImage($snapshot['signature_image'] ?? null),
            'background_image' => $this->resolveTemplateImage($snapshot['background_image'] ?? null),
            'footer_note' => $snapshot['footer_note'] ?? 'This certificate is generated automatically by the system.',
        ];
    }

    private function buildSnapshot(
        Enrollment $enrollment,
        CertificateSetting $settings,
        string $certificateNumber,
        string $verificationCode,
        Carbon $issuedAt,
        ?Carbon $expiredAt
    ): array {
        return [
            'organization_name' => $settings->organization_name,
            'certificate_title' => $settings->certificate_title,
            'student_name' => $enrollment->user?->fullname ?: $enrollment->user?->email ?: 'Student',
            'course_title' => $enrollment->courseOffering?->course?->title ?: 'Course',
            'certificate_number' => $certificateNumber,
            'issue_date' => $this->formatCertificateDate($issuedAt),
            'issued_at' => $this->formatCertificateDateTime($issuedAt),
            'expired_at' => $this->formatCertificateDateTime($expiredAt),
            'verification_code' => $verificationCode,
            'signatory_name' => $settings->signatory_name,
            'signatory_title' => $settings->signatory_title,
            'signature_image' => $settings->signature_image,
            'background_image' => $settings->background_image,
            'footer_note' => $settings->footer_note,
        ];
    }

    private function calculateExpiredAt(Carbon $issuedAt, CertificateSetting $settings): ?Carbon
    {
        if (! $settings->expires_after_months) {
            return null;
        }

        return $issuedAt->copy()->addMonths((int) $settings->expires_after_months);
    }

    private function generateCertificateNumber(CertificateSetting $settings): string
    {
        $prefix = Str::upper(Str::slug($settings->certificate_prefix ?: 'CERT', '-')) ?: 'CERT';

        do {
            $number = $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(10));
        } while (Certificate::where('certificate_number', $number)->exists());

        return $number;
    }

    private function generateVerificationCode(): string
    {
        do {
            $code = strtoupper(Str::random(16));
        } while (Certificate::where('verification_code', $code)->exists());

        return $code;
    }

    private function resolveTemplateImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['data:', 'http://', 'https://'])) {
            return $path;
        }

        if (file_exists($path)) {
            return $path;
        }

        if (Str::startsWith($path, '/storage/')) {
            $storagePath = storage_path('app/public/' . Str::after($path, '/storage/'));

            return file_exists($storagePath) ? $storagePath : null;
        }

        $publicPath = public_path(ltrim($path, '/'));

        return file_exists($publicPath) ? $publicPath : null;
    }

    private function formatCertificateDate(?Carbon $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value->copy()
            ->setTimezone(self::CERTIFICATE_DISPLAY_TIMEZONE)
            ->format('d M Y');
    }

    private function formatCertificateDateTime(?Carbon $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value->copy()
            ->setTimezone(self::CERTIFICATE_DISPLAY_TIMEZONE)
            ->format('d M Y, H.i') . ' WIB';
    }

    private function downloadUrlPlaceholder(): string
    {
        return '__pending_download_url__';
    }

    private function assertCanManageOffering(?CourseOffering $offering, User $actor): void
    {
        if (! $offering) {
            throw ValidationException::withMessages([
                'course_offering' => ['Certificate is not linked to a valid course offering.'],
            ]);
        }

        $actor->loadMissing('role');
        $roleName = $actor->role?->name;

        if ($roleName === 'admin') {
            return;
        }

        $offering->loadMissing('course');
        if ($roleName === 'instructor' && (int) $offering->course?->instructor_id === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'certificate' => ['You do not have permission to manage this certificate.'],
        ]);
    }
}
