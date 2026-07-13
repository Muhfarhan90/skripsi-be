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
use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    protected OrderService $orderService;
    protected AssignmentService $assignmentService;
    protected CourseSnapshotService $courseSnapshotService;

    public function __construct(
        OrderService $orderService,
        AssignmentService $assignmentService,
        CourseSnapshotService $courseSnapshotService
    )
    {
        $this->orderService = $orderService;
        $this->assignmentService = $assignmentService;
        $this->courseSnapshotService = $courseSnapshotService;
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
            'passed_quiz_ids' => $progress['passed_quiz_ids'] ?? [],
            'approved_assignment_ids' => $progress['approved_assignment_ids'] ?? [],
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
        $completedLessonIds = LessonProgress::where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')
            ->pluck('lesson_id')
            ->map(fn ($lessonId) => (int) $lessonId)
            ->all();

        foreach ($this->getOrderedLessonIdsForEnrollment($enrollment) as $lessonId) {
            if (in_array($lessonId, $completedLessonIds, true)) {
                continue;
            }

            $lesson = $this->findVisibleLessonForEnrollment($enrollment, $lessonId);
            if ($lesson !== null) {
                return $lesson;
            }
        }

        return null;
    }

    public function getCurriculumCourseForUser(int $userId, int $enrollmentId): Course
    {
        $enrollment = $this->findByIdForUser($userId, $enrollmentId);
        $this->assertCanReadMaterial($enrollment);

        return $this->getVisibleCurriculumCourseForEnrollment($enrollment);
    }

    public function findLessonDetailForUser(int $userId, int $enrollmentId, int $lessonId): array
    {
        $enrollment = $this->findByIdForUser($userId, $enrollmentId);
        $this->assertCanReadMaterial($enrollment);
        $this->assertLessonUnlockedForEnrollment($enrollment, $lessonId);
        $lesson = $this->findVisibleLessonForEnrollment($enrollment, $lessonId);

        if ($lesson === null) {
            throw ValidationException::withMessages([
                'lesson_id' => ['Lesson tidak tersedia untuk enrollment ini.'],
            ]);
        }

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
        $this->assertQuizUnlockedForEnrollment($enrollment, $quizId);
        $quizSnapshot = $this->findQuizSnapshotForEnrollment($enrollment, $quizId);
        $quiz = $this->findVisibleQuizForEnrollment($enrollment, $quizId, true, true);

        if ($quiz === null) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Quiz tidak tersedia untuk enrollment ini.'],
            ]);
        }

        $attempt = QuizAttempt::where('enrollment_id', $enrollment->id)
            ->where('quiz_id', $quizId)
            ->latest('id')
            ->first();

        if ($attempt && QuizAnswer::where('attempt_id', $attempt->id)->exists()) {
            $answers = QuizAnswer::where('attempt_id', $attempt->id)
                ->orderBy('id')
                ->get();

            $questionSnapshots = $answers
                ->map(function (QuizAnswer $answer) use ($quizSnapshot): ?array {
                    if (is_array($answer->question_snapshot)) {
                        return $answer->question_snapshot;
                    }

                    if ($quizSnapshot === null) {
                        return null;
                    }

                    return $this->courseSnapshotService->findQuestionSnapshot($quizSnapshot, (int) $answer->question_id);
                })
                ->filter()
                ->values();

            $quiz->setRelation(
                'questions',
                $questionSnapshots
                    ->map(fn (array $questionSnapshot) => $this->courseSnapshotService->makeQuestionModel($questionSnapshot))
                    ->values()
            );
        } elseif ($quizSnapshot !== null) {
            $quiz->setRelation(
                'questions',
                collect($quizSnapshot['questions'] ?? [])
                    ->map(fn (array $questionSnapshot) => $this->courseSnapshotService->makeQuestionModel($questionSnapshot))
                    ->values()
            );
        }

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
        $this->assertItemUnlockedForEnrollment($enrollment, 'lesson', $lessonId);
    }

    public function assertQuizUnlockedForEnrollment(Enrollment $enrollment, int $quizId): void
    {
        $this->assertItemUnlockedForEnrollment($enrollment, 'quiz', $quizId);
    }

    public function assertAssignmentUnlockedForEnrollment(Enrollment $enrollment, int $assignmentId): void
    {
        $this->assertItemUnlockedForEnrollment($enrollment, 'assignment', $assignmentId);
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

        $quizGradeItems = collect($snapshot['quiz_grade_items'] ?? [])
            ->filter(fn ($item) => is_array($item) && isset($item['quiz_id']))
            ->values();

        if (! $hasQuizSnapshot || $quizGradeItems->isEmpty()) {
            $courseId = $this->resolveCourseId($enrollment);
            $quizGradeItems = Quiz::query()
                ->where('course_id', $courseId)
                ->where('is_active', true)
                ->get(['id', 'passing_score', 'weight'])
                ->map(fn (Quiz $quiz) => [
                    'quiz_id' => (int) $quiz->id,
                    'passing_score' => $quiz->passing_score !== null ? (int) $quiz->passing_score : null,
                    'weight' => (int) ($quiz->weight ?? 0),
                ])
                ->values();
        }

        $quizPassingScoreMap = $quizGradeItems
            ->mapWithKeys(fn (array $item) => [
                (int) $item['quiz_id'] => array_key_exists('passing_score', $item) && $item['passing_score'] !== null
                    ? (int) $item['passing_score']
                    : null,
            ]);

        $quizIds = $quizPassingScoreMap->keys()->map(fn ($id) => (int) $id)->values();
        $totalQuizzes = $quizIds->count();
        $passedQuizIds = QuizAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('quiz_id', $quizIds->all())
            ->where('status', 'graded')
            ->get(['quiz_id', 'total_score'])
            ->filter(function (QuizAttempt $attempt) use ($quizPassingScoreMap): bool {
                $passingScore = $quizPassingScoreMap->get((int) $attempt->quiz_id);

                return $passingScore === null || (int) $attempt->total_score >= $passingScore;
            })
            ->pluck('quiz_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $completedQuizzes = count($passedQuizIds);

        $assignmentIds = collect($snapshot['assignment_ids'] ?? []);
        if (! $hasAssignmentSnapshot) {
            $courseId = $this->resolveCourseId($enrollment);
            $assignmentIds = Assignment::query()
                ->where('course_id', $courseId)
                ->where('status', 'published')
                ->pluck('id');
        }

        $totalAssignments = $assignmentIds->count();
        $approvedAssignmentIds = AssignmentSubmission::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->where('status', 'approved')
            ->pluck('assignment_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $completedAssignments = count($approvedAssignmentIds);

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
            'passed_quiz_ids' => $passedQuizIds,
            'approved_assignment_ids' => $approvedAssignmentIds,
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
            $result['passed_quiz_ids'] = $quizIds->map(fn ($id) => (int) $id)->values()->all();
            $result['approved_assignment_ids'] = $assignmentIds->map(fn ($id) => (int) $id)->values()->all();
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
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $snapshotLessonIds = collect($snapshot['lesson_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_key_exists('lesson_ids', $snapshot)) {
            return $snapshotLessonIds;
        }

        $courseId = $this->resolveCourseId($enrollment);
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
        if ($this->findLessonSnapshotForEnrollment($enrollment, $lessonId) !== null) {
            return true;
        }

        $courseId = $this->resolveCourseId($enrollment);

        return Lesson::withTrashed()
            ->where('id', $lessonId)
            ->whereHas('section', function ($query) use ($courseId) {
                $query->withTrashed()->where('course_id', $courseId);
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

        return $this->courseSnapshotService->buildForCourseId(
            $courseId,
            $enrollment->courseOffering?->id ? (int) $enrollment->courseOffering->id : null,
            $existingSnapshot,
        );
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
        return $this->courseSnapshotService->needsRefresh($snapshot);
    }

    private function normalizeSnapshotIdList(mixed $value): ?array
    {
        return $this->courseSnapshotService->normalizeIdList($value);
    }

    public function getOrderedLearningContentsForEnrollment(Enrollment $enrollment): array
    {
        $snapshot = $this->getCompletionSnapshot($enrollment);

        if (is_array($snapshot['ordered_items'] ?? null)) {
            return collect($snapshot['ordered_items'])
                ->filter(fn ($item) => is_array($item) && isset($item['type'], $item['id']))
                ->map(fn (array $item) => [
                    'type' => (string) $item['type'],
                    'id' => (int) $item['id'],
                    'section_id' => isset($item['section_id']) ? (int) $item['section_id'] : null,
                ])
                ->values()
                ->all();
        }

        return [];
    }

    public function assertItemUnlockedForEnrollment(Enrollment $enrollment, string $type, int $itemId): void
    {
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $quizPassingScoreMap = collect($snapshot['quiz_grade_items'] ?? [])
            ->filter(fn ($item) => is_array($item) && isset($item['quiz_id']))
            ->mapWithKeys(fn (array $item) => [
                (int) $item['quiz_id'] => array_key_exists('passing_score', $item) && $item['passing_score'] !== null
                    ? (int) $item['passing_score']
                    : null,
            ]);

        $completedLessonIds = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')
            ->pluck('lesson_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $passedQuizIds = QuizAttempt::query()
            ->where('quiz_attempts.enrollment_id', $enrollment->id)
            ->where('quiz_attempts.status', 'graded')
            ->get(['quiz_id', 'total_score'])
            ->filter(function (QuizAttempt $attempt) use ($quizPassingScoreMap): bool {
                $passingScore = $quizPassingScoreMap->get((int) $attempt->quiz_id);

                return $passingScore === null || (int) $attempt->total_score >= $passingScore;
            })
            ->pluck('quiz_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $approvedAssignmentIds = AssignmentSubmission::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', 'approved')
            ->pluck('assignment_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $orderedItems = $this->getOrderedLearningContentsForEnrollment($enrollment);

        $targetIndex = -1;
        foreach ($orderedItems as $index => $item) {
            if ($item['type'] === $type && $item['id'] === $itemId) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex === -1) {
            return;
        }

        if ($targetIndex === 0) {
            return;
        }

        $precedingItems = array_slice($orderedItems, 0, $targetIndex);

        foreach ($precedingItems as $item) {
            if ($item['type'] === 'lesson') {
                if (! in_array($item['id'], $completedLessonIds, true)) {
                    throw ValidationException::withMessages([
                        'lesson_id' => ['Selesaikan lesson sebelumnya terlebih dahulu.'],
                    ]);
                }
            } elseif ($item['type'] === 'quiz') {
                if (! in_array($item['id'], $passedQuizIds, true)) {
                    throw ValidationException::withMessages([
                        'quiz_id' => ['Selesaikan kuis sebelumnya terlebih dahulu.'],
                    ]);
                }
            } elseif ($item['type'] === 'assignment') {
                if (! in_array($item['id'], $approvedAssignmentIds, true)) {
                    throw ValidationException::withMessages([
                        'assignment_id' => ['Selesaikan tugas sebelumnya terlebih dahulu.'],
                    ]);
                }
            }
        }
    }

    public function getSnapshotForEnrollment(Enrollment $enrollment): array
    {
        return $this->getCompletionSnapshot($enrollment);
    }

    public function getVisibleCurriculumCourseForEnrollment(Enrollment $enrollment): Course
    {
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $course = $this->courseSnapshotService->loadCourseForCurriculum($this->resolveCourseId($enrollment));
        $state = $this->buildLearningStateForEnrollment($enrollment, $snapshot);

        return $this->courseSnapshotService->makeCourseModel(
            $this->buildVisibleCurriculumSnapshot($course, $snapshot, $state)
        );
    }

    public function findVisibleLessonForEnrollment(Enrollment $enrollment, int $lessonId): ?Lesson
    {
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $state = $this->buildLearningStateForEnrollment($enrollment, $snapshot);
        $lessonSnapshot = $this->findLessonSnapshotForEnrollment($enrollment, $lessonId);

        if ($lessonSnapshot !== null) {
            $liveLesson = $this->findLiveLessonForEnrollmentCourse($enrollment, $lessonId, true);
            $sectionSnapshot = $this->findSectionSnapshotForItem($snapshot, 'lesson', $lessonId);
            $resolvedLessonSnapshot = $liveLesson
                ? $this->courseSnapshotService->makeLessonSnapshotFromModel($liveLesson)
                : $lessonSnapshot;
            $decoratedLesson = $this->makeLessonModelFromSnapshot(
                $this->decorateLessonSnapshotForEnrollment(
                    $resolvedLessonSnapshot,
                    false,
                    $liveLesson ? 'live' : 'snapshot_fallback',
                    $state
                )
            );

            if ($sectionSnapshot !== null) {
                $decoratedLesson->setRelation(
                    'section',
                    $this->courseSnapshotService->makeSectionModel(
                        $this->decorateCountedSectionSnapshot(
                            $sectionSnapshot,
                            $this->findLiveSectionSourceForSnapshot($enrollment, $sectionSnapshot)
                        )
                    )
                );
            }

            return $decoratedLesson;
        }

        $liveLesson = $this->findLiveLessonForEnrollmentCourse($enrollment, $lessonId, true);
        if (! $liveLesson) {
            return null;
        }

        $lesson = $this->makeLessonModelFromSnapshot(
            $this->decorateLessonSnapshotForEnrollment(
                $this->courseSnapshotService->makeLessonSnapshotFromModel($liveLesson),
                true,
                'live',
                $state
            )
        );

        if ($liveLesson->relationLoaded('section') && $liveLesson->section) {
            $lesson->setRelation(
                'section',
                $this->courseSnapshotService->makeSectionModel([
                    'id' => (int) $liveLesson->section->id,
                    'course_id' => (int) $liveLesson->section->course_id,
                    'title' => $liveLesson->section->title,
                    'sort_order' => $liveLesson->section->sort_order !== null ? (int) $liveLesson->section->sort_order : null,
                    'lessons' => [],
                    'quizzes' => [],
                    'assignments' => [],
                    'is_supplemental' => ! $this->sectionExistsInSnapshot($snapshot, (int) $liveLesson->section->id),
                    'source' => 'live',
                ])
            );
        }

        return $lesson;
    }

    public function findVisibleQuizForEnrollment(
        Enrollment $enrollment,
        int $quizId,
        bool $withQuestions = true,
        bool $allowInactive = true
    ): ?Quiz
    {
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $state = $this->buildLearningStateForEnrollment($enrollment, $snapshot);
        $quizSnapshot = $this->findQuizSnapshotForEnrollment($enrollment, $quizId);

        if ($quizSnapshot !== null) {
            $quiz = $this->makeQuizModelFromSnapshot(
                $this->decorateQuizSnapshotForEnrollment($quizSnapshot, false, 'snapshot', $state),
                $withQuestions
            );

            if (! $allowInactive && ! $quiz->is_active) {
                throw ValidationException::withMessages([
                    'quiz_id' => ['Quiz is not available for a new attempt.'],
                ]);
            }

            return $quiz;
        }

        $liveQuiz = $this->findLiveQuizForEnrollmentCourse($enrollment, $quizId, $allowInactive, $withQuestions);
        if (! $liveQuiz) {
            return null;
        }

        return $this->makeQuizModelFromSnapshot(
            $this->decorateQuizSnapshotForEnrollment(
                $this->courseSnapshotService->makeQuizSnapshotFromModel($liveQuiz),
                true,
                'live',
                $state
            ),
            $withQuestions
        );
    }

    public function findVisibleAssignmentForEnrollment(Enrollment $enrollment, int $assignmentId): ?Assignment
    {
        $snapshot = $this->getCompletionSnapshot($enrollment);
        $state = $this->buildLearningStateForEnrollment($enrollment, $snapshot);
        $assignmentSnapshot = $this->findAssignmentSnapshotForEnrollment($enrollment, $assignmentId);

        if ($assignmentSnapshot !== null) {
            $sectionSnapshot = $this->findSectionSnapshotForItem($snapshot, 'assignment', $assignmentId);
            $assignment = $this->makeAssignmentModelFromSnapshot(
                $this->decorateAssignmentSnapshotForEnrollment($assignmentSnapshot, false, 'snapshot', $state)
            );

            if ($sectionSnapshot !== null) {
                $assignment->setRelation(
                    'section',
                    $this->courseSnapshotService->makeSectionModel(
                        $this->decorateCountedSectionSnapshot(
                            $sectionSnapshot,
                            $this->findLiveSectionSourceForSnapshot($enrollment, $sectionSnapshot)
                        )
                    )
                );
            }

            return $assignment;
        }

        $liveAssignment = $this->findLiveAssignmentForEnrollmentCourse($enrollment, $assignmentId);
        if (! $liveAssignment) {
            return null;
        }

        $assignment = $this->makeAssignmentModelFromSnapshot(
            $this->decorateAssignmentSnapshotForEnrollment(
                $this->courseSnapshotService->makeAssignmentSnapshotFromModel($liveAssignment),
                true,
                'live',
                $state
            )
        );

        if ($liveAssignment->relationLoaded('section') && $liveAssignment->section) {
            $assignment->setRelation(
                'section',
                $this->courseSnapshotService->makeSectionModel([
                    'id' => (int) $liveAssignment->section->id,
                    'course_id' => (int) $liveAssignment->section->course_id,
                    'title' => $liveAssignment->section->title,
                    'sort_order' => $liveAssignment->section->sort_order !== null ? (int) $liveAssignment->section->sort_order : null,
                    'lessons' => [],
                    'quizzes' => [],
                    'assignments' => [],
                    'is_supplemental' => ! $this->sectionExistsInSnapshot($snapshot, (int) $liveAssignment->section->id),
                    'source' => 'live',
                ])
            );
        }

        return $assignment;
    }

    public function findLessonSnapshotForEnrollment(Enrollment $enrollment, int $lessonId): ?array
    {
        return $this->courseSnapshotService->findLessonSnapshot($this->getCompletionSnapshot($enrollment), $lessonId);
    }

    public function findQuizSnapshotForEnrollment(Enrollment $enrollment, int $quizId): ?array
    {
        return $this->courseSnapshotService->findQuizSnapshot($this->getCompletionSnapshot($enrollment), $quizId);
    }

    public function findAssignmentSnapshotForEnrollment(Enrollment $enrollment, int $assignmentId): ?array
    {
        return $this->courseSnapshotService->findAssignmentSnapshot($this->getCompletionSnapshot($enrollment), $assignmentId);
    }

    public function makeLessonModelFromSnapshot(array $lessonSnapshot): Lesson
    {
        return $this->courseSnapshotService->makeLessonModel($lessonSnapshot);
    }

    public function makeQuizModelFromSnapshot(array $quizSnapshot, bool $withQuestions = true): Quiz
    {
        return $this->courseSnapshotService->makeQuizModel($quizSnapshot, $withQuestions);
    }

    public function makeAssignmentModelFromSnapshot(array $assignmentSnapshot): Assignment
    {
        return $this->courseSnapshotService->makeAssignmentModel($assignmentSnapshot);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function buildVisibleCurriculumSnapshot(Course $course, array $snapshot, array $state): array
    {
        $liveSections = $course->sections->keyBy(fn (Section $section) => (int) $section->id);
        $snapshotLessonIds = collect($snapshot['lesson_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();
        $snapshotQuizIds = collect($snapshot['quiz_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();
        $snapshotAssignmentIds = collect($snapshot['assignment_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();
        $snapshotSectionIds = [];
        $sections = [];

        foreach ($snapshot['sections'] ?? [] as $sectionSnapshot) {
            if (! is_array($sectionSnapshot)) {
                continue;
            }

            $sectionId = (int) ($sectionSnapshot['id'] ?? 0);
            if ($sectionId <= 0) {
                continue;
            }

            $snapshotSectionIds[] = $sectionId;
            $liveSection = $liveSections->get($sectionId);

            $sectionData = $this->decorateCountedSectionSnapshot(
                $sectionSnapshot,
                $liveSection ? 'live' : 'snapshot_fallback'
            );
            $sectionData['title'] = $liveSection?->title ?? ($sectionSnapshot['title'] ?? null);
            $sectionData['sort_order'] = (int) ($sectionSnapshot['sort_order'] ?? 0);
            $sectionData['lessons'] = collect($sectionSnapshot['lessons'] ?? [])
                ->filter(fn ($lesson) => is_array($lesson))
                ->map(function (array $lessonSnapshot) use ($course, $state): array {
                    $lessonId = (int) ($lessonSnapshot['id'] ?? 0);
                    $liveLesson = $course->sections
                        ->flatMap(fn (Section $section) => $section->lessons)
                        ->first(fn (Lesson $lesson) => (int) $lesson->id === $lessonId && (string) $lesson->status === 'published');

                    $resolvedSnapshot = $liveLesson
                        ? $this->courseSnapshotService->makeLessonSnapshotFromModel($liveLesson)
                        : $lessonSnapshot;

                    return $this->decorateLessonSnapshotForEnrollment(
                        $resolvedSnapshot,
                        false,
                        $liveLesson ? 'live' : 'snapshot_fallback',
                        $state
                    );
                })
                ->values()
                ->all();
            $sectionData['quizzes'] = collect($sectionSnapshot['quizzes'] ?? [])
                ->filter(fn ($quiz) => is_array($quiz))
                ->map(fn (array $quizSnapshot) => $this->decorateQuizSnapshotForEnrollment($quizSnapshot, false, 'snapshot', $state))
                ->values()
                ->all();
            $sectionData['assignments'] = collect($sectionSnapshot['assignments'] ?? [])
                ->filter(fn ($assignment) => is_array($assignment))
                ->map(fn (array $assignmentSnapshot) => $this->decorateAssignmentSnapshotForEnrollment($assignmentSnapshot, false, 'snapshot', $state))
                ->values()
                ->all();

            if ($liveSection) {
                foreach ($liveSection->lessons as $lesson) {
                    if ((string) $lesson->status !== 'published' || in_array((int) $lesson->id, $snapshotLessonIds, true)) {
                        continue;
                    }

                    $sectionData['lessons'][] = $this->decorateLessonSnapshotForEnrollment(
                        $this->courseSnapshotService->makeLessonSnapshotFromModel($lesson),
                        true,
                        'live',
                        $state
                    );
                }

                foreach ($liveSection->quizzes as $quiz) {
                    if (! $quiz->is_active || in_array((int) $quiz->id, $snapshotQuizIds, true)) {
                        continue;
                    }

                    $sectionData['quizzes'][] = $this->decorateQuizSnapshotForEnrollment(
                        $this->courseSnapshotService->makeQuizSnapshotFromModel($quiz),
                        true,
                        'live',
                        $state
                    );
                }

                foreach ($liveSection->assignments as $assignment) {
                    if ((string) $assignment->status !== 'published' || in_array((int) $assignment->id, $snapshotAssignmentIds, true)) {
                        continue;
                    }

                    $sectionData['assignments'][] = $this->decorateAssignmentSnapshotForEnrollment(
                        $this->courseSnapshotService->makeAssignmentSnapshotFromModel($assignment),
                        true,
                        'live',
                        $state
                    );
                }
            }

            $sections[] = $sectionData;
        }

        foreach ($course->sections as $section) {
            if (in_array((int) $section->id, $snapshotSectionIds, true)) {
                continue;
            }

            $lessonSnapshots = $section->lessons
                ->filter(fn (Lesson $lesson) => (string) $lesson->status === 'published')
                ->map(fn (Lesson $lesson) => $this->decorateLessonSnapshotForEnrollment(
                    $this->courseSnapshotService->makeLessonSnapshotFromModel($lesson),
                    true,
                    'live',
                    $state
                ))
                ->values()
                ->all();

            $quizSnapshots = $section->quizzes
                ->filter(fn (Quiz $quiz) => (bool) $quiz->is_active)
                ->map(fn (Quiz $quiz) => $this->decorateQuizSnapshotForEnrollment(
                    $this->courseSnapshotService->makeQuizSnapshotFromModel($quiz),
                    true,
                    'live',
                    $state
                ))
                ->values()
                ->all();

            $assignmentSnapshots = $section->assignments
                ->filter(fn (Assignment $assignment) => (string) $assignment->status === 'published')
                ->map(fn (Assignment $assignment) => $this->decorateAssignmentSnapshotForEnrollment(
                    $this->courseSnapshotService->makeAssignmentSnapshotFromModel($assignment),
                    true,
                    'live',
                    $state
                ))
                ->values()
                ->all();

            if ($lessonSnapshots === [] && $quizSnapshots === [] && $assignmentSnapshots === []) {
                continue;
            }

            $sections[] = [
                'id' => (int) $section->id,
                'course_id' => (int) $section->course_id,
                'title' => $section->title,
                'sort_order' => $section->sort_order !== null ? (int) $section->sort_order : null,
                'lessons' => $lessonSnapshots,
                'quizzes' => $quizSnapshots,
                'assignments' => $assignmentSnapshots,
                'is_supplemental' => true,
                'source' => 'live',
            ];
        }

        return [
            'course_id' => (int) $course->id,
            'course_offering_id' => $snapshot['course_offering_id'] ?? null,
            'course' => $this->buildCourseSnapshotPayload($course),
            'sections' => $sections,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCourseSnapshotPayload(Course $course): array
    {
        return [
            'id' => (int) $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'description' => $course->description,
            'category_id' => $course->category_id !== null ? (int) $course->category_id : null,
            'category_name' => $course->relationLoaded('category') ? $course->category?->name : null,
            'instructor_id' => $course->instructor_id !== null ? (int) $course->instructor_id : null,
            'instructor_name' => $course->relationLoaded('instructor') ? $course->instructor?->fullname : null,
            'thumbnail' => $course->thumbnail,
            'requirements' => $course->requirements,
            'outcomes' => $course->outcomes,
            'status' => $course->getAttribute('status'),
            'skills' => $course->relationLoaded('skills')
                ? $course->skills->map(fn ($skill) => [
                    'id' => (int) $skill->id,
                    'name' => $skill->name,
                    'slug' => $skill->slug,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decorateCountedSectionSnapshot(array $sectionSnapshot, string $source): array
    {
        return array_merge($sectionSnapshot, [
            'is_supplemental' => false,
            'source' => $source,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function decorateLessonSnapshotForEnrollment(
        array $lessonSnapshot,
        bool $isSupplemental,
        string $source,
        array $state
    ): array
    {
        return $this->attachLearningItemMeta('lesson', $lessonSnapshot, $isSupplemental, $source, $state);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function decorateQuizSnapshotForEnrollment(
        array $quizSnapshot,
        bool $isSupplemental,
        string $source,
        array $state
    ): array
    {
        return $this->attachLearningItemMeta('quiz', $quizSnapshot, $isSupplemental, $source, $state);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function decorateAssignmentSnapshotForEnrollment(
        array $assignmentSnapshot,
        bool $isSupplemental,
        string $source,
        array $state
    ): array
    {
        return $this->attachLearningItemMeta('assignment', $assignmentSnapshot, $isSupplemental, $source, $state);
    }

    /**
     * @param  array<string, mixed>  $itemSnapshot
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function attachLearningItemMeta(
        string $type,
        array $itemSnapshot,
        bool $isSupplemental,
        string $source,
        array $state
    ): array
    {
        $itemId = (int) ($itemSnapshot['id'] ?? 0);
        $quizAttempts = $state['quiz_attempts_by_quiz_id']->get($itemId, collect());

        $isCompleted = match ($type) {
            'lesson' => in_array($itemId, $state['completed_lesson_ids'], true),
            'quiz' => $this->hasPassedQuizAttempts($quizAttempts, $itemSnapshot['passing_score'] ?? null),
            'assignment' => in_array($itemId, $state['approved_assignment_ids'], true),
            default => false,
        };

        $hasUserActivity = match ($type) {
            'lesson' => $state['lesson_progress_by_lesson_id']->has($itemId),
            'quiz' => $state['quiz_attempts_by_quiz_id']->has($itemId),
            'assignment' => $state['assignment_submissions_by_assignment_id']->has($itemId),
            default => false,
        };

        $isNew = match ($type) {
            'lesson', 'quiz', 'assignment' => $isSupplemental && ! $hasUserActivity,
            default => false,
        };

        return array_merge($itemSnapshot, [
            'is_supplemental' => $isSupplemental,
            'counts_toward_progress' => ! $isSupplemental,
            'counts_toward_certificate' => ! $isSupplemental,
            'source' => $source,
            'is_locked' => ! $isSupplemental && isset($state['locked_items'][$this->buildLearningItemKey($type, $itemId)]),
            'is_new' => $isNew,
            'is_completed' => $isCompleted,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>
     */
    private function buildLearningStateForEnrollment(Enrollment $enrollment, ?array $snapshot = null): array
    {
        $snapshot = $snapshot ?? $this->getCompletionSnapshot($enrollment);
        $lessonProgressCollection = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->get(['lesson_id', 'progress_seconds', 'last_accessed_at', 'completed_at']);
        $completedLessonIds = $lessonProgressCollection
            ->filter(fn (LessonProgress $progress) => $progress->completed_at !== null)
            ->pluck('lesson_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $quizPassingScoreMap = collect($snapshot['quiz_grade_items'] ?? [])
            ->filter(fn ($item) => is_array($item) && isset($item['quiz_id']))
            ->mapWithKeys(fn (array $item) => [
                (int) $item['quiz_id'] => array_key_exists('passing_score', $item) && $item['passing_score'] !== null
                    ? (int) $item['passing_score']
                    : null,
            ]);
        $quizAttemptsByQuizId = QuizAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get(['id', 'quiz_id', 'status', 'total_score', 'started_at', 'submitted_at'])
            ->groupBy(fn (QuizAttempt $attempt) => (int) $attempt->quiz_id);
        $passedQuizIds = $quizPassingScoreMap->keys()
            ->filter(fn ($quizId) => $this->hasPassedQuizAttempts(
                $quizAttemptsByQuizId->get((int) $quizId, collect()),
                $quizPassingScoreMap->get((int) $quizId)
            ))
            ->map(fn ($quizId) => (int) $quizId)
            ->values()
            ->all();
        $assignmentSubmissionsByAssignmentId = AssignmentSubmission::query()
            ->where('enrollment_id', $enrollment->id)
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->get(['id', 'assignment_id', 'status', 'attempt_no'])
            ->groupBy(fn (AssignmentSubmission $submission) => (int) $submission->assignment_id);
        $approvedAssignmentIds = $assignmentSubmissionsByAssignmentId->keys()
            ->filter(function ($assignmentId) use ($assignmentSubmissionsByAssignmentId): bool {
                return $assignmentSubmissionsByAssignmentId
                    ->get((int) $assignmentId, collect())
                    ->contains(fn (AssignmentSubmission $submission) => (string) $submission->status === 'approved');
            })
            ->map(fn ($assignmentId) => (int) $assignmentId)
            ->values()
            ->all();

        return [
            'lesson_progress_by_lesson_id' => $lessonProgressCollection->keyBy(fn (LessonProgress $progress) => (int) $progress->lesson_id),
            'completed_lesson_ids' => $completedLessonIds,
            'quiz_attempts_by_quiz_id' => $quizAttemptsByQuizId,
            'approved_assignment_ids' => $approvedAssignmentIds,
            'assignment_submissions_by_assignment_id' => $assignmentSubmissionsByAssignmentId,
            'locked_items' => $this->buildLockedItemMap($snapshot, $completedLessonIds, $passedQuizIds, $approvedAssignmentIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int>  $completedLessonIds
     * @param  array<int>  $passedQuizIds
     * @param  array<int>  $approvedAssignmentIds
     * @return array<string, bool>
     */
    private function buildLockedItemMap(
        array $snapshot,
        array $completedLessonIds,
        array $passedQuizIds,
        array $approvedAssignmentIds
    ): array
    {
        $lockedItems = [];
        $previousItemCompleted = true;

        foreach ($this->getOrderedItemsFromSnapshot($snapshot) as $item) {
            if (! $previousItemCompleted) {
                $lockedItems[$this->buildLearningItemKey($item['type'], $item['id'])] = true;
            }

            $previousItemCompleted = match ($item['type']) {
                'lesson' => in_array($item['id'], $completedLessonIds, true),
                'quiz' => in_array($item['id'], $passedQuizIds, true),
                'assignment' => in_array($item['id'], $approvedAssignmentIds, true),
                default => true,
            };
        }

        return $lockedItems;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array{type:string,id:int,section_id:int|null}>
     */
    private function getOrderedItemsFromSnapshot(array $snapshot): array
    {
        return collect($snapshot['ordered_items'] ?? [])
            ->filter(fn ($item) => is_array($item) && isset($item['type'], $item['id']))
            ->map(fn (array $item) => [
                'type' => (string) $item['type'],
                'id' => (int) $item['id'],
                'section_id' => isset($item['section_id']) ? (int) $item['section_id'] : null,
            ])
            ->values()
            ->all();
    }

    private function buildLearningItemKey(string $type, int $itemId): string
    {
        return sprintf('%s:%d', $type, $itemId);
    }

    private function hasPassedQuizAttempts(Collection $attempts, mixed $passingScore): bool
    {
        $gradedAttempts = $attempts->filter(fn (QuizAttempt $attempt) => (string) $attempt->status === 'graded');

        if ($gradedAttempts->isEmpty()) {
            return false;
        }

        if ($passingScore === null) {
            return true;
        }

        return $gradedAttempts->contains(fn (QuizAttempt $attempt) => (int) $attempt->total_score >= (int) $passingScore);
    }

    private function findLiveLessonForEnrollmentCourse(
        Enrollment $enrollment,
        int $lessonId,
        bool $publishedOnly = true
    ): ?Lesson
    {
        $courseId = $this->resolveCourseId($enrollment);
        $query = Lesson::withTrashed()
            ->with(['section' => fn ($builder) => $builder->withTrashed()])
            ->where('id', $lessonId)
            ->whereHas('section', function ($builder) use ($courseId) {
                $builder->withTrashed()->where('course_id', $courseId);
            });

        if ($publishedOnly) {
            $query->whereNull('deleted_at')->where('status', 'published');
        }

        return $query->first();
    }

    private function findLiveQuizForEnrollmentCourse(
        Enrollment $enrollment,
        int $quizId,
        bool $allowInactive = true,
        bool $withQuestions = true
    ): ?Quiz
    {
        $courseId = $this->resolveCourseId($enrollment);
        $query = Quiz::withTrashed()
            ->with([
                'section' => fn ($builder) => $builder->withTrashed(),
                'questions' => function ($builder) use ($withQuestions) {
                    if (! $withQuestions) {
                        return;
                    }

                    $builder->withTrashed()->orderBy('sort_order')->orderBy('id');
                },
                'questions.options' => fn ($builder) => $builder->withTrashed()->orderBy('id'),
            ])
            ->where('id', $quizId)
            ->where('course_id', $courseId);

        if (! $allowInactive) {
            $query->whereNull('deleted_at')->where('is_active', true);
        }

        return $query->first();
    }

    private function findLiveAssignmentForEnrollmentCourse(Enrollment $enrollment, int $assignmentId): ?Assignment
    {
        $courseId = $this->resolveCourseId($enrollment);

        return Assignment::withTrashed()
            ->with(['section' => fn ($builder) => $builder->withTrashed()])
            ->where('id', $assignmentId)
            ->where('course_id', $courseId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function sectionExistsInSnapshot(array $snapshot, int $sectionId): bool
    {
        foreach ($snapshot['sections'] ?? [] as $section) {
            if ((int) ($section['id'] ?? 0) === $sectionId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $sectionSnapshot
     */
    private function findLiveSectionSourceForSnapshot(Enrollment $enrollment, array $sectionSnapshot): string
    {
        $sectionId = (int) ($sectionSnapshot['id'] ?? 0);
        if ($sectionId <= 0) {
            return 'snapshot_fallback';
        }

        $courseId = $this->resolveCourseId($enrollment);

        return Section::withTrashed()
            ->where('id', $sectionId)
            ->where('course_id', $courseId)
            ->exists()
                ? 'live'
                : 'snapshot_fallback';
    }

    private function findSectionSnapshotForItem(array $snapshot, string $type, int $itemId): ?array
    {
        $collectionKey = match ($type) {
            'lesson' => 'lessons',
            'quiz' => 'quizzes',
            'assignment' => 'assignments',
            default => null,
        };

        if ($collectionKey === null) {
            return null;
        }

        foreach ($snapshot['sections'] ?? [] as $section) {
            foreach ($section[$collectionKey] ?? [] as $item) {
                if ((int) ($item['id'] ?? 0) === $itemId) {
                    return $section;
                }
            }
        }

        return null;
    }
}
