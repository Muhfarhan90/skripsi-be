<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LessonProgressService
{
    protected EnrollmentService $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }

    public function getByEnrollment(int $userId, int $enrollmentId)
    {
        $this->findEnrollmentForUser($userId, $enrollmentId);

        return LessonProgress::where('enrollment_id', $enrollmentId)
            ->latest()
            ->paginate(10);
    }

    public function getByEnrollmentForAdmin(int $enrollmentId)
    {
        $this->findEnrollmentForAdmin($enrollmentId);

        return LessonProgress::where('enrollment_id', $enrollmentId)
            ->latest()
            ->paginate(10);
    }

    public function findByEnrollmentAndLessonForUser(int $userId, int $enrollmentId, int $lessonId): LessonProgress
    {
        $this->findEnrollmentForUser($userId, $enrollmentId);

        return LessonProgress::where('enrollment_id', $enrollmentId)
            ->where('lesson_id', $lessonId)
            ->firstOrFail();
    }

    public function findByEnrollmentAndLessonForAdmin(int $enrollmentId, int $lessonId): LessonProgress
    {
        $this->findEnrollmentForAdmin($enrollmentId);

        return LessonProgress::where('enrollment_id', $enrollmentId)
            ->where('lesson_id', $lessonId)
            ->firstOrFail();
    }

    public function upsertByEnrollmentAndLesson(int $userId, int $enrollmentId, int $lessonId, array $data): LessonProgress
    {
        $enrollment = $this->findEnrollmentForUser($userId, $enrollmentId);
        $this->enrollmentService->assertCanWriteLearning($enrollment);

        return $this->persistProgress($enrollment, $lessonId, $data);
    }

    public function upsertForAdmin(int $enrollmentId, int $lessonId, array $data): LessonProgress
    {
        $enrollment = $this->findEnrollmentForAdmin($enrollmentId);

        return $this->persistProgress($enrollment, $lessonId, $data);
    }

    private function findEnrollmentForUser(int $userId, int $enrollmentId): Enrollment
    {
        return Enrollment::where('id', $enrollmentId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    private function findEnrollmentForAdmin(int $enrollmentId): Enrollment
    {
        return Enrollment::findOrFail($enrollmentId);
    }

    private function persistProgress(Enrollment $enrollment, int $lessonId, array $data): LessonProgress
    {
        $lesson = Lesson::with('section')->findOrFail($lessonId);
        $enrollment->loadMissing('courseOffering');
        $courseId = $enrollment->courseOffering?->course_id;

        if (! $lesson->section || (int) $lesson->section->course_id !== (int) $courseId) {
            throw ValidationException::withMessages([
                'lesson_id' => ['Lesson does not belong to the enrolled course'],
            ]);
        }

        return DB::transaction(function () use ($data, $enrollment, $lesson) {
            $existingProgress = LessonProgress::where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->first();

            $progressSeconds = array_key_exists('progress_seconds', $data)
                ? (int) $data['progress_seconds']
                : ((int) ($existingProgress?->progress_seconds ?? 0));

            $completedAt = $existingProgress?->completed_at;
            if (array_key_exists('completed_at', $data)) {
                $completedAt = $data['completed_at'];
            }

            $progress = LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'progress_seconds' => $progressSeconds,
                    'last_accessed_at' => now(),
                    'completed_at' => $completedAt,
                ]
            );

            $enrollment->update([
                'last_lesson_id' => $lesson->id,
            ]);

            $this->enrollmentService->syncProgress($enrollment->id);

            return $progress->fresh();
        });
    }
}
