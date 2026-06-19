<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    protected OrderService $orderService;
    protected AssignmentService $assignmentService;

    public function __construct(
        OrderService $orderService,
        AssignmentService $assignmentService
    )
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
                fn (Enrollment $enrollment) => $this->syncProgress($enrollment->id)->fresh([
                    'courseOffering.course.category:id,name',
                    'courseOffering.course.instructor:id,fullname',
                    'courseOffering.academicPeriod',
                    'order',
                    'certificate',
                ])
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
        $progress = $this->calculateLearningProgress($enrollment);
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $assignmentRequirement = $this->assignmentService->getCompletionRequirementSummary($enrollment);
        $hasCertificate = $this->hasCertificateForEnrollment($enrollment);
        $canGenerateCertificate = $hasCertificate
            || (
                (int) $progress['progress'] >= 100
                && (string) $enrollment->status === 'completed'
                && (bool) ($assignmentRequirement['is_satisfied'] ?? false)
            );
        $certificateBlockReason = null;

        if (! $canGenerateCertificate) {
            if ((int) $progress['progress'] < 100) {
                $certificateBlockReason = 'Progress belum 100%.';
            } elseif ((string) $enrollment->status !== 'completed') {
                $certificateBlockReason = 'Enrollment belum completed.';
            } elseif (! (bool) ($assignmentRequirement['is_satisfied'] ?? false)) {
                $certificateBlockReason = 'Assignment wajib belum terpenuhi.';
            } else {
                $certificateBlockReason = 'Sertifikat belum memenuhi syarat generate.';
            }
        }

        return [
            'enrollment_id' => $enrollment->id,
            'total_items' => $progress['total_items'],
            'completed_items' => $progress['completed_items'],
            'remaining_items' => $progress['remaining_items'],
            'total_lessons' => $progress['total_lessons'],
            'completed_lessons' => $progress['completed_lessons'],
            'remaining_lessons' => $progress['remaining_lessons'],
            'total_quizzes' => $progress['total_quizzes'],
            'completed_quizzes' => $progress['completed_quizzes'],
            'remaining_quizzes' => $progress['remaining_quizzes'],
            'total_assignments' => $progress['total_assignments'],
            'completed_assignments' => $progress['completed_assignments'],
            'remaining_assignments' => $progress['remaining_assignments'],
            'progress' => $progress['progress'],
            'status' => $enrollment->status,
            'has_certificate' => $hasCertificate,
            'can_generate_certificate' => $canGenerateCertificate,
            'certificate_block_reason' => $certificateBlockReason,
            'completion_snapshot' => $snapshot,
            'completed_at' => $enrollment->completed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'started_at' => $enrollment->started_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'ended_at' => $this->getEffectiveEndedAt($enrollment)?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'assignment_requirement' => $assignmentRequirement,
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
        $course = Course::withTrashed()->findOrFail($courseId);

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
                $syncedEnrollment = $this->syncProgress($enrollment->id)->fresh([
                    'user:id,fullname,email',
                    'certificate',
                ]);

                $syncedEnrollment->setAttribute(
                    'assignment_requirement',
                    $this->assignmentService->getCompletionRequirementSummary($syncedEnrollment)
                );

                return $syncedEnrollment;
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
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $progress = $this->calculateLearningProgress($enrollment)['progress'];
        $hasCertificate = $this->hasCertificateForEnrollment($enrollment);

        $payload = [
            'progress' => $progress,
            'completion_snapshot' => $snapshot,
        ];
        $effectiveEndedAt = $this->getEffectiveEndedAt($enrollment);
        $completionRequirementMet = $this->assignmentService->isCompletionRequirementMet($enrollment);
        $completionCriteriaMet = $progress >= 100
            && $completionRequirementMet;

        if ($hasCertificate) {
            $payload['status'] = 'completed';
            $payload['completed_at'] = $enrollment->completed_at ?? now();
        } elseif ($completionCriteriaMet) {
            $payload['status'] = 'completed';
            $payload['completed_at'] = $enrollment->completed_at ?? now();
        } elseif ((string) $enrollment->status !== 'cancelled') {
            $payload['completed_at'] = null;

            if ($enrollment->started_at && now()->lt($enrollment->started_at)) {
                $payload['status'] = 'pending';
            } elseif ($effectiveEndedAt && now()->gt($effectiveEndedAt)) {
                $payload['status'] = 'expired';
            } else {
                $payload['status'] = 'active';
            }
        }

        $enrollment->update($payload);
        
        return $enrollment->fresh(['certificate']);
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
            if ($this->lessonBelongsToEnrollmentCourse($enrollment, $lessonId)) {
                return;
            }

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

        return $enrollment->certificate()->exists();
    }

    private function calculateLearningProgress(Enrollment $enrollment): array
    {
        $courseId = $this->resolveCourseId($enrollment);
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $hasLessonSnapshot = array_key_exists('lesson_ids', $snapshot);
        $hasQuizSnapshot = array_key_exists('quiz_ids', $snapshot);
        $hasAssignmentSnapshot = array_key_exists('assignment_ids', $snapshot);

        $lessonIds = collect($snapshot['lesson_ids'] ?? []);
        if (! $hasLessonSnapshot) {
            $lessonIds = Lesson::query()
                ->select('lessons.id')
                ->join('sections', 'sections.id', '=', 'lessons.section_id')
                ->where('sections.course_id', $courseId)
                ->where('lessons.status', 'published')
                ->pluck('lessons.id');
        }

        $totalLessons = $lessonIds->count();
        $completedLessons = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $lessonIds)
            ->whereNotNull('completed_at')
            ->count();

        $quizIds = collect($snapshot['quiz_ids'] ?? []);
        $quizzes = $hasQuizSnapshot
            ? Quiz::query()
                ->where('course_id', $courseId)
                ->whereIn('id', $quizIds)
                ->get(['id', 'passing_score'])
            : Quiz::query()
                ->where('course_id', $courseId)
                ->where('is_active', true)
                ->get(['id', 'passing_score']);

        $totalQuizzes = $quizzes->count();
        $completedQuizzes = $quizzes->filter(function (Quiz $quiz) use ($enrollment): bool {
            $attemptQuery = QuizAttempt::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('quiz_id', $quiz->id)
                ->where('status', 'graded');

            if ($quiz->passing_score !== null) {
                $attemptQuery->where('total_score', '>=', (int) $quiz->passing_score);
            }

            return $attemptQuery->exists();
        })->count();

        $assignmentIds = collect($snapshot['assignment_ids'] ?? []);
        if (! $hasAssignmentSnapshot) {
            $assignmentIds = Assignment::query()
                ->where('course_id', $courseId)
                ->where('status', 'published')
                ->pluck('id');
        }

        $totalAssignments = $assignmentIds->count();
        $completedAssignments = AssignmentSubmission::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->where('status', 'approved')
            ->distinct('assignment_id')
            ->count('assignment_id');

        $totalItems = $totalLessons + $totalQuizzes + $totalAssignments;
        $completedItems = $completedLessons + $completedQuizzes + $completedAssignments;
        $progress = $totalItems > 0
            ? (int) floor(($completedItems / $totalItems) * 100)
            : 0;

        $result = [
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            'remaining_items' => max(0, $totalItems - $completedItems),
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'remaining_lessons' => max(0, $totalLessons - $completedLessons),
            'total_quizzes' => $totalQuizzes,
            'completed_quizzes' => $completedQuizzes,
            'remaining_quizzes' => max(0, $totalQuizzes - $completedQuizzes),
            'total_assignments' => $totalAssignments,
            'completed_assignments' => $completedAssignments,
            'remaining_assignments' => max(0, $totalAssignments - $completedAssignments),
            'progress' => $progress,
        ];

        if ($enrollment->status === 'completed' || $this->hasCertificateForEnrollment($enrollment)) {
            $result['completed_items'] = $result['total_items'];
            $result['remaining_items'] = 0;
            $result['completed_lessons'] = $result['total_lessons'];
            $result['remaining_lessons'] = 0;
            $result['completed_quizzes'] = $result['total_quizzes'];
            $result['remaining_quizzes'] = 0;
            $result['completed_assignments'] = $result['total_assignments'];
            $result['remaining_assignments'] = 0;
            $result['progress'] = 100;
        }

        return $result;
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
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $snapshotLessonIds = collect($snapshot['lesson_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_key_exists('lesson_ids', $snapshot)) {
            return $snapshotLessonIds;
        }

        return Lesson::query()
            ->select('lessons.id')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $courseId)
            ->where('lessons.status', 'published')
            ->orderBy('sections.sort_order')
            ->orderBy('sections.id')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->pluck('lessons.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function lessonBelongsToEnrollmentCourse(Enrollment $enrollment, int $lessonId): bool
    {
        $courseId = $this->resolveCourseId($enrollment);

        return Lesson::query()
            ->where('id', $lessonId)
            ->whereHas('section', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->exists();
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

    private function getCompletionSnapshot(Enrollment $enrollment): array
    {
        if (is_array($enrollment->completion_snapshot) && ! $this->completionSnapshotNeedsRefresh($enrollment->completion_snapshot)) {
            return $enrollment->completion_snapshot;
        }

        $existingSnapshot = is_array($enrollment->completion_snapshot) ? $enrollment->completion_snapshot : null;
        $snapshot = $this->buildCompletionSnapshot($enrollment, $existingSnapshot);
        $enrollment->forceFill(['completion_snapshot' => $snapshot])->save();
        $enrollment->completion_snapshot = $snapshot;

        return $snapshot;
    }

    private function buildCompletionSnapshot(Enrollment $enrollment, ?array $existingSnapshot = null): array
    {
        $courseId = $this->resolveCourseId($enrollment);
        Course::withTrashed()->select(['id'])->findOrFail($courseId);
        $enrollment->loadMissing('courseOffering');
        $snapshotLessonIds = $this->normalizeSnapshotIdList($existingSnapshot['lesson_ids'] ?? null);
        $lessonIds = $snapshotLessonIds
            ?? Lesson::query()
                ->select('lessons.id')
                ->join('sections', 'sections.id', '=', 'lessons.section_id')
                ->where('sections.course_id', $courseId)
                ->where('lessons.status', 'published')
                ->orderBy('sections.sort_order')
                ->orderBy('sections.id')
                ->orderBy('lessons.sort_order')
                ->orderBy('lessons.id')
                ->pluck('lessons.id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $snapshotQuizIds = $this->normalizeSnapshotIdList($existingSnapshot['quiz_ids'] ?? null);
        $quizQuery = Quiz::query()
            ->where('course_id', $courseId);

        if ($snapshotQuizIds !== null) {
            $quizQuery->whereIn('id', $snapshotQuizIds);
        } else {
            $quizQuery->where('is_active', true);
        }

        $quizzes = $quizQuery
            ->orderBy('id')
            ->get(['id', 'passing_score', 'weight'])
            ->keyBy('id');

        $quizIds = $snapshotQuizIds !== null
            ? collect($snapshotQuizIds)
                ->filter(fn ($quizId) => $quizzes->has($quizId))
                ->values()
                ->all()
            : $quizzes->keys()->map(fn ($id) => (int) $id)->values()->all();

        $snapshotAssignmentIds = $this->normalizeSnapshotIdList($existingSnapshot['assignment_ids'] ?? null);
        $assignmentQuery = Assignment::query()
            ->where('course_id', $courseId)
            ->when(
                $snapshotAssignmentIds !== null,
                fn ($query) => $query->whereIn('id', $snapshotAssignmentIds),
                fn ($query) => $query->where('status', 'published')
            );

        $assignments = $assignmentQuery
            ->orderBy('due_at')
            ->orderBy('id')
            ->get([
                'id',
                'is_required_for_certificate',
            ])
            ->keyBy('id');

        $assignmentIds = $snapshotAssignmentIds !== null
            ? collect($snapshotAssignmentIds)
                ->filter(fn ($assignmentId) => $assignments->has($assignmentId))
                ->values()
                ->all()
            : $assignments->keys()->map(fn ($id) => (int) $id)->values()->all();

        $snapshotRequiredAssignmentIds = $this->normalizeSnapshotIdList($existingSnapshot['required_assignment_ids'] ?? null);
        $requiredAssignmentIds = $snapshotRequiredAssignmentIds !== null
            ? collect($snapshotRequiredAssignmentIds)
                ->filter(fn ($assignmentId) => in_array($assignmentId, $assignmentIds, true))
                ->values()
                ->all()
            : collect($assignmentIds)
                ->filter(fn ($assignmentId) => (bool) optional($assignments->get($assignmentId))->is_required_for_certificate)
                ->values()
                ->all();

        $quizGradeItems = collect($quizIds)
            ->map(function (int $quizId) use ($quizzes): ?array {
                $quiz = $quizzes->get($quizId);

                if (! $quiz) {
                    return null;
                }

                return [
                    'quiz_id' => (int) $quiz->id,
                    'weight' => (int) ($quiz->weight ?? 0),
                    'passing_score' => $quiz->passing_score !== null ? (int) $quiz->passing_score : null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $assignmentGradeItems = collect($assignmentIds)
            ->map(function (int $assignmentId) use ($assignments): ?array {
                $assignment = $assignments->get($assignmentId);

                if (! $assignment) {
                    return null;
                }

                return [
                    'assignment_id' => (int) $assignment->id,
                    'is_required_for_certificate' => (bool) $assignment->is_required_for_certificate,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'course_id' => $courseId,
            'course_offering_id' => $enrollment->courseOffering?->id,
            'lesson_ids' => $lessonIds,
            'quiz_ids' => $quizIds,
            'assignment_ids' => $assignmentIds,
            'required_assignment_ids' => $requiredAssignmentIds,
            'quiz_grade_items' => $quizGradeItems,
            'assignment_grade_items' => $assignmentGradeItems,
            'snapshot_at' => $existingSnapshot['snapshot_at'] ?? now()->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function assertCompletionRequirementSatisfied(Enrollment $enrollment): void
    {
        $summary = $this->assignmentService->getCompletionRequirementSummary($enrollment);
        $messages = [];

        if (! $summary['is_satisfied']) {
            $messages['assignment'] = [
                sprintf(
                    'Required assignments approved: %d/%d. Certificate is blocked until all required assignments are approved.',
                    (int) $summary['approved_assignments'],
                    (int) $summary['required_assignments']
                ),
            ];
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function completionSnapshotNeedsRefresh(?array $snapshot): bool
    {
        if (! is_array($snapshot)) {
            return true;
        }

        return ! isset(
            $snapshot['course_id'],
            $snapshot['course_offering_id'],
            $snapshot['lesson_ids'],
            $snapshot['quiz_ids'],
            $snapshot['assignment_ids'],
            $snapshot['required_assignment_ids'],
            $snapshot['quiz_grade_items'],
            $snapshot['assignment_grade_items'],
            $snapshot['snapshot_at'],
        )
            || ! is_array($snapshot['quiz_grade_items'])
            || ! is_array($snapshot['assignment_grade_items']);
    }

    private function normalizeSnapshotIdList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = [];

        foreach ($value as $id) {
            $normalizedId = (int) $id;

            if ($normalizedId <= 0 || in_array($normalizedId, $normalized, true)) {
                continue;
            }

            $normalized[] = $normalizedId;
        }

        return $normalized;
    }
}
