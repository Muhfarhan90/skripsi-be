<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    protected OrderService $orderService;
    protected AssignmentService $assignmentService;

    public function __construct(OrderService $orderService, AssignmentService $assignmentService)
    {
        $this->orderService = $orderService;
        $this->assignmentService = $assignmentService;
    }

    public function getAllByUser(int $userId)
    {
        $enrollments = Enrollment::with([
            'courseOffering.course.category:id,name',
            'courseOffering.course.instructor:id,fullname',
            'courseOffering.academicPeriod',
            'order',
            'certificate',
        ])->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('order_id')
                    ->orWhereHas('order', function ($orderQuery) {
                        $orderQuery->where('status', 'completed');
                    });
            })
            ->latest()
            ->paginate(10);

        $enrollments->setCollection(
            $enrollments->getCollection()->map(
                fn (Enrollment $enrollment) => $this->normalizePaidEnrollmentAccess($enrollment)
            )
        );

        return $enrollments;
    }

    public function findByIdForUser(int $userId, int $id): Enrollment
    {
        $enrollment = Enrollment::with([
            'courseOffering.course.category:id,name',
            'courseOffering.course.instructor:id,fullname',
            'courseOffering.academicPeriod',
            'order',
            'certificate',
        ])->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('order_id')
                    ->orWhereHas('order', function ($orderQuery) {
                        $orderQuery->where('status', 'completed');
                    });
            })
            ->findOrFail($id);

        return $this->normalizePaidEnrollmentAccess($enrollment);
    }

    public function complete(int $userId, int $id): Enrollment
    {
        $ownedEnrollment = $this->findByIdForUser($userId, $id);
        $this->assertCanWriteLearning($ownedEnrollment);

        $enrollment = $this->syncProgress($ownedEnrollment->id);
        if ($enrollment->progress < 100) {
            throw ValidationException::withMessages([
                'progress' => ['Course progress must be 100% before completion'],
            ]);
        }

        $this->assertCompletionRequirementSatisfied($enrollment);

        if ($enrollment->status !== 'completed') {
            $enrollment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $enrollment = $enrollment->fresh();
        }

        $this->ensureCertificateGenerated($enrollment);

        return $enrollment->fresh([
            'courseOffering.course.category:id,name',
            'courseOffering.course.instructor:id,fullname',
            'courseOffering.academicPeriod',
            'order',
            'certificate',
        ]);
    }

    public function progressSummary(int $userId, int $id): array
    {
        $ownedEnrollment = $this->findByIdForUser($userId, $id);
        $enrollment = $this->syncProgress($ownedEnrollment->id);
        $courseId = $this->resolveCourseId($enrollment);

        $totalLessons = Lesson::whereHas('section', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })->count();

        $completedLessons = LessonProgress::where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')
            ->count();

        return [
            'enrollment_id' => $enrollment->id,
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'remaining_lessons' => max(0, $totalLessons - $completedLessons),
            'progress' => $enrollment->progress,
            'status' => $enrollment->status,
            'has_certificate' => $this->hasCertificateForEnrollment($enrollment),
            'completed_at' => $enrollment->completed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'started_at' => $enrollment->started_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'ended_at' => $this->getEffectiveEndedAt($enrollment)?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'assignment_requirement' => $this->assignmentService->getCompletionRequirementSummary($enrollment),
        ];
    }

    public function nextLesson(int $userId, int $id): ?Lesson
    {
        $enrollment = $this->findByIdForUser($userId, $id);
        $this->assertCanReadMaterial($enrollment);
        $courseId = $this->resolveCourseId($enrollment);

        $completedLessonIds = LessonProgress::where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')
            ->pluck('lesson_id');

        return Lesson::query()
            ->select('lessons.*')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $courseId)
            ->whereNotIn('lessons.id', $completedLessonIds)
            ->orderBy('sections.sort_order')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->first();
    }

    public function findLessonDetailForUser(int $userId, int $enrollmentId, int $lessonId): array
    {
        $enrollment = $this->findByIdForUser($userId, $enrollmentId);
        $this->assertCanReadMaterial($enrollment);
        $courseId = $this->resolveCourseId($enrollment);
        $this->assertLessonUnlockedForEnrollment($enrollment, $lessonId);

        $lesson = Lesson::with('section')
            ->where('id', $lessonId)
            ->whereHas('section', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->firstOrFail();

        $progress = LessonProgress::where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        return [
            'enrollment' => $enrollment,
            'lesson' => $lesson,
            'progress' => $progress,
        ];
    }

    public function findQuizDetailForUser(int $userId, int $enrollmentId, int $quizId): array
    {
        $enrollment = $this->findByIdForUser($userId, $enrollmentId);
        $this->assertCanReadMaterial($enrollment);
        $courseId = $this->resolveCourseId($enrollment);

        $quiz = Quiz::query()
            ->with([
                'questions' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'questions.options' => fn ($query) => $query->orderBy('id'),
            ])
            ->where('id', $quizId)
            ->where('course_id', $courseId)
            ->firstOrFail();

        $unsupportedQuestionTypes = $quiz->questions
            ->pluck('type')
            ->filter(fn ($type) => ! in_array((string) $type, ['multiple_choice', 'true_false'], true))
            ->unique()
            ->values()
            ->all();

        return [
            'enrollment' => $enrollment,
            'quiz' => $quiz,
            'is_supported' => count($unsupportedQuestionTypes) === 0,
            'unsupported_question_types' => $unsupportedQuestionTypes,
        ];
    }

    public function getAllForAdmin()
    {
        return Enrollment::with([
            'courseOffering.course',
            'courseOffering.academicPeriod',
            'certificate',
        ])->latest()->paginate(10);
    }

    public function findByIdForAdmin(int $id): Enrollment
    {
        return Enrollment::with([
            'courseOffering.course',
            'courseOffering.academicPeriod',
            'order',
            'certificate',
        ])->findOrFail($id);
    }

    public function getByCourseIdForAdmin(int $courseId)
    {
        $course = Course::findOrFail($courseId);

        return Enrollment::whereHas('courseOffering', function ($query) use ($course) {
            $query->where('course_id', $course->id);
        })
            ->with(['courseOffering.course', 'courseOffering.academicPeriod', 'certificate'])
            ->latest()
            ->paginate(10);
    }

    public function getByCourseOfferingIdForAdmin(int $offeringId, int $perPage = 10, string $search = '')
    {
        $perPage = max($perPage, 1);

        $enrollments = Enrollment::query()
            ->with([
                'user:id,fullname,email',
                'certificate',
            ])
            ->where('course_offering_id', $offeringId)
            ->where(function ($query) {
                $query->whereNull('order_id')
                    ->orWhereHas('order', function ($orderQuery) {
                        $orderQuery->where('status', 'completed');
                    });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('fullname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $enrollments->setCollection(
            $enrollments->getCollection()->map(function (Enrollment $enrollment) {
                $enrollment->setAttribute(
                    'assignment_requirement',
                    $this->assignmentService->getCompletionRequirementSummary($enrollment)
                );

                return $enrollment;
            })
        );

        return $enrollments;
    }

    public function updateStatusForAdmin(int $id, string $status): Enrollment
    {
        $enrollment = $this->findByIdForAdmin($id);

        $payload = ['status' => $status];
        if ($status === 'completed') {
            $this->assertCompletionRequirementSatisfied($enrollment);
            $payload['completed_at'] = now();
            $payload['progress'] = 100;
        }
        if ($status === 'active') {
            $payload['completed_at'] = null;
        }
        if ($status === 'expired') {
            $payload['ended_at'] = $this->getEffectiveEndedAt($enrollment) ?? now();
            $payload['expired_at'] = $payload['ended_at'];
        }

        $enrollment->update($payload);
        $enrollment = $enrollment->fresh();

        if ($enrollment->status === 'completed') {
            $this->ensureCertificateGenerated($enrollment);
        }

        return $enrollment->fresh([
            'courseOffering.course',
            'courseOffering.academicPeriod',
            'order',
            'certificate',
        ]);
    }

    public function syncProgress(int $enrollmentId): Enrollment
    {
        $enrollment = Enrollment::with(['order', 'courseOffering.academicPeriod', 'certificate'])->findOrFail($enrollmentId);
        $enrollment = $this->normalizePaidEnrollmentAccess($enrollment);
        $courseId = $this->resolveCourseId($enrollment);

        $totalLessons = Lesson::whereHas('section', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })->count();

        $completedLessons = LessonProgress::where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')
            ->count();

        $progress = $totalLessons > 0
            ? (int) floor(($completedLessons / $totalLessons) * 100)
            : 0;

        $payload = ['progress' => $progress];
        $effectiveEndedAt = $this->getEffectiveEndedAt($enrollment);
        $completionRequirementMet = $this->assignmentService->isCompletionRequirementMet($enrollment);

        if ($progress >= 100 && $completionRequirementMet) {
            $payload['status'] = 'completed';
            $payload['completed_at'] = $enrollment->completed_at ?? now();
        } elseif ($enrollment->status !== 'completed') {
            if ($effectiveEndedAt && now()->gt($effectiveEndedAt) && $enrollment->status !== 'completed') {
                $payload['status'] = 'expired';
            } elseif (
                in_array((string) $enrollment->status, ['pending', 'expired'], true)
                && $this->isWithinWindow($enrollment)
            ) {
                $payload['status'] = 'active';
            }
        }

        $enrollment->update($payload);
        $enrollment = $enrollment->fresh(['certificate']);

        if ($enrollment->status === 'completed') {
            $this->ensureCertificateGenerated($enrollment);
            $enrollment = $enrollment->fresh(['certificate']);
        }

        return $enrollment;
    }

    public function assertCanReadMaterial(Enrollment $enrollment): void
    {
        if ($this->canReadMaterial($enrollment)) {
            return;
        }

        throw ValidationException::withMessages([
            'enrollment_id' => ['Learning material access is locked for this enrollment.'],
        ]);
    }

    public function assertCanWriteLearning(Enrollment $enrollment): void
    {
        if ($this->canWriteLearning($enrollment)) {
            return;
        }

        throw ValidationException::withMessages([
            'enrollment_id' => ['Learning activity is not allowed outside the active enrollment period.'],
        ]);
    }

    public function assertLessonUnlockedForEnrollment(Enrollment $enrollment, int $lessonId): void
    {
        $orderedLessonIds = $this->getOrderedLessonIdsForEnrollment($enrollment);
        $targetIndex = array_search($lessonId, $orderedLessonIds, true);

        if ($targetIndex === false) {
            throw ValidationException::withMessages([
                'lesson_id' => ['Lesson does not belong to the enrolled course'],
            ]);
        }

        if ($targetIndex === 0) {
            return;
        }

        $previousLessonIds = array_slice($orderedLessonIds, 0, $targetIndex);
        $completedLessonIds = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $previousLessonIds)
            ->whereNotNull('completed_at')
            ->pluck('lesson_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count(array_diff($previousLessonIds, $completedLessonIds)) === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'lesson_id' => ['Selesaikan lesson sebelumnya terlebih dahulu.'],
        ]);
    }

    public function canReadMaterial(Enrollment $enrollment): bool
    {
        if ($this->hasCertificateForEnrollment($enrollment)) {
            return true;
        }

        if (! in_array((string) $enrollment->status, ['active', 'completed'], true)) {
            return false;
        }

        return $this->isWithinWindow($enrollment);
    }

    public function canWriteLearning(Enrollment $enrollment): bool
    {
        if (! in_array((string) $enrollment->status, ['active', 'completed'], true)) {
            return false;
        }

        return $this->isWithinWindow($enrollment);
    }

    private function isWithinWindow(Enrollment $enrollment): bool
    {
        $now = now();
        if ($enrollment->started_at && $now->lt($enrollment->started_at)) {
            return false;
        }

        $effectiveEndedAt = $this->getEffectiveEndedAt($enrollment);
        if ($effectiveEndedAt && $now->gt($effectiveEndedAt)) {
            return false;
        }

        return true;
    }

    private function getEffectiveEndedAt(Enrollment $enrollment): ?Carbon
    {
        if ($enrollment->ended_at) {
            return $enrollment->ended_at;
        }

        return $enrollment->expired_at;
    }

    private function hasCertificateForEnrollment(Enrollment $enrollment): bool
    {
        if ($enrollment->relationLoaded('certificate')) {
            return $enrollment->certificate !== null;
        }

        return Certificate::where('enrollment_id', $enrollment->id)->exists();
    }

    private function ensureCertificateGenerated(Enrollment $enrollment): void
    {
        if (! $this->assignmentService->isCompletionRequirementMet($enrollment)) {
            return;
        }

        $certificate = Certificate::where('enrollment_id', $enrollment->id)->first();
        if ($certificate) {
            return;
        }

        $issuedAt = now();
        $certificateNumber = 'CERT-' . $issuedAt->format('Ymd') . '-' . strtoupper(Str::random(10));

        Certificate::create([
            'user_id' => $enrollment->user_id,
            'course_id' => $this->resolveCourseId($enrollment),
            'enrollment_id' => $enrollment->id,
            'certificate_number' => $certificateNumber,
            'certificate_url' => url('/certificates/' . $certificateNumber . '.pdf'),
            'issued_at' => $issuedAt,
            'expired_at' => null,
        ]);
    }

    private function resolveCourseId(Enrollment $enrollment): int
    {
        $enrollment->loadMissing('courseOffering');
        if (! $enrollment->courseOffering || ! $enrollment->courseOffering->course_id) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Enrollment is missing a valid course offering reference.'],
            ]);
        }

        return (int) $enrollment->courseOffering->course_id;
    }

    private function getOrderedLessonIdsForEnrollment(Enrollment $enrollment): array
    {
        $courseId = $this->resolveCourseId($enrollment);

        return Lesson::query()
            ->select('lessons.id')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $courseId)
            ->orderBy('sections.sort_order')
            ->orderBy('sections.id')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->pluck('lessons.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function normalizePaidEnrollmentAccess(Enrollment $enrollment): Enrollment
    {
        $enrollment->loadMissing(['order', 'courseOffering.academicPeriod', 'certificate']);

        $order = $enrollment->order;
        if (! $order || $order->status !== 'completed') {
            return $enrollment;
        }

        $now = now();
        $effectiveEndedAt = $this->getEffectiveEndedAt($enrollment);
        $payload = [];

        if (! $enrollment->started_at || $enrollment->started_at->gt($now)) {
            $payload['started_at'] = $now;
        }

        if ($effectiveEndedAt && $now->gt($effectiveEndedAt)) {
            if ($enrollment->status !== 'completed') {
                $payload['status'] = 'expired';
            }
        } elseif (! in_array((string) $enrollment->status, ['active', 'completed'], true)) {
            $payload['status'] = 'active';
        }

        if ($payload === []) {
            return $enrollment;
        }

        $enrollment->update($payload);

        return $enrollment->fresh([
            'courseOffering.course.category:id,name',
            'courseOffering.course.instructor:id,fullname',
            'courseOffering.academicPeriod',
            'order',
            'certificate',
        ]);
    }

    private function assertCompletionRequirementSatisfied(Enrollment $enrollment): void
    {
        $summary = $this->assignmentService->getCompletionRequirementSummary($enrollment);
        if ($summary['is_satisfied']) {
            return;
        }

        throw ValidationException::withMessages([
            'assignment' => [
                sprintf(
                    'Required assignments approved: %d/%d. Certificate is blocked until all required assignments are approved.',
                    (int) $summary['approved_assignments'],
                    (int) $summary['required_assignments']
                ),
            ],
        ]);
    }
}
