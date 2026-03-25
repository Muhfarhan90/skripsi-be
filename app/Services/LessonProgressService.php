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

    public function findByIdForUser(int $userId, int $id): LessonProgress
    {
        return LessonProgress::whereHas('enrollment', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->findOrFail($id);
    }

    public function upsert(int $userId, array $data): LessonProgress
    {
        $enrollment = $this->findEnrollmentForUser($userId, (int) $data['enrollment_id']);
        $lesson = Lesson::with('section')->findOrFail((int) $data['lesson_id']);

        if (! $lesson->section || (int) $lesson->section->course_id !== (int) $enrollment->course_id) {
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

    private function findEnrollmentForUser(int $userId, int $enrollmentId): Enrollment
    {
        return Enrollment::where('id', $enrollmentId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }
}
