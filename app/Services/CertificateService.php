<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    protected AssignmentService $assignmentService;

    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Student: Mendapatkan semua sertifikat miliknya.
     */
    public function getUserCertificates(User $user)
    {
        return Certificate::where('user_id', $user->id)
            ->with('course')
            ->latest()
            ->paginate(15);
    }

    /**
     * Student: Mendapatkan sertifikat berdasarkan Enrollment ID.
     */
    public function getCertificateByEnrollment(int $enrollmentId, User $user): ?Certificate
    {
        $enrollment = Enrollment::where('user_id', $user->id)->findOrFail($enrollmentId);

        return Certificate::where('enrollment_id', $enrollment->id)->first();
    }

    /**
     * Student: Generate sertifikat untuk kursus yang sudah diselesaikan.
     */
    public function generateCertificate(int $enrollmentId, User $user): Certificate
    {
        $enrollment = Enrollment::where('user_id', $user->id)->findOrFail($enrollmentId);

        if ($enrollment->status !== 'completed') {
            throw ValidationException::withMessages([
                'enrollment_id' => ['You must complete the course to get a certificate.'],
            ]);
        }

        if (! $this->assignmentService->isCompletionRequirementMet($enrollment)) {
            throw ValidationException::withMessages([
                'assignment' => ['Required assignment approval is not complete yet.'],
            ]);
        }

        // Cek apakah sudah ada sertifikat
        $existingCertificate = Certificate::where('enrollment_id', $enrollment->id)->first();

        if ($existingCertificate) {
            return $existingCertificate; // Langsung return jika sudah ada
        }

        // TODO: Disini nantinya bisa integrasi dengan PDF Generator / Image Generator (seperti Browsershot atau DomPDF)
        $issuedAt = Carbon::now();
        $certificateNumber = 'CERT-' . $issuedAt->format('Ymd') . '-' . strtoupper(uniqid());
        $dummyUrl = url('/certificates/' . $certificateNumber . '.pdf');
        $enrollment->loadMissing('courseOffering');
        $courseId = $enrollment->courseOffering?->course_id;
        if (! $courseId) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Enrollment is missing a valid course offering reference.'],
            ]);
        }

        $certificate = Certificate::create([
            'user_id' => $user->id,
            'course_id' => $courseId,
            'enrollment_id' => $enrollment->id,
            'certificate_number' => $certificateNumber,
            'certificate_url' => $dummyUrl,
            'issued_at' => $issuedAt,
            'expired_at' => null,
        ]);

        return $certificate->load('course', 'user');
    }
}
