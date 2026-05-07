<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Order;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function getAllByUser(int $userId)
    {
        return Enrollment::with([
            'course.category:id,name',
            'course.instructor:id,fullname',
        ])->where('user_id', $userId)
            ->latest()
            ->paginate(10);
    }

    public function findByIdForUser(int $userId, int $id): Enrollment
    {
        return Enrollment::with([
            'course.category:id,name',
            'course.instructor:id,fullname',
            'order',
        ])->where('user_id', $userId)
            ->findOrFail($id);
    }

    public function complete(int $userId, int $id): Enrollment
    {
        $ownedEnrollment = $this->findByIdForUser($userId, $id);
        $enrollment = $this->syncProgress($ownedEnrollment->id);

        if ($enrollment->progress < 100) {
            throw ValidationException::withMessages([
                'progress' => ['Course progress must be 100% before completion'],
            ]);
        }

        if ($enrollment->status !== 'completed') {
            $enrollment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return $enrollment->fresh();
    }

    public function progressSummary(int $userId, int $id): array
    {
        $ownedEnrollment = $this->findByIdForUser($userId, $id);
        $enrollment = $this->syncProgress($ownedEnrollment->id);

        $totalLessons = Lesson::whereHas('section', function ($query) use ($enrollment) {
            $query->where('course_id', $enrollment->course_id);
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
            'completed_at' => $enrollment->completed_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function nextLesson(int $userId, int $id): ?Lesson
    {
        $enrollment = $this->findByIdForUser($userId, $id);

        $completedLessonIds = LessonProgress::where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')
            ->pluck('lesson_id');

        return Lesson::query()
            ->select('lessons.*')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $enrollment->course_id)
            ->whereNotIn('lessons.id', $completedLessonIds)
            ->orderBy('sections.sort_order')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->first();
    }

    public function findLessonDetailForUser(int $userId, int $enrollmentId, int $lessonId): array
    {
        $enrollment = $this->findByIdForUser($userId, $enrollmentId);

        $lesson = Lesson::with('section')
            ->where('id', $lessonId)
            ->whereHas('section', function ($query) use ($enrollment) {
                $query->where('course_id', $enrollment->course_id);
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

    public function getAllForAdmin()
    {
        return Enrollment::latest()->paginate(10);
    }

    public function findByIdForAdmin(int $id): Enrollment
    {
        return Enrollment::findOrFail($id);
    }

    public function getByCourseIdForAdmin(int $courseId)
    {
        $course = Course::findOrFail($courseId);

        return Enrollment::where('course_id', $course->id)
            ->latest()
            ->paginate(10);
    }

    public function updateStatusForAdmin(int $id, string $status): Enrollment
    {
        $enrollment = $this->findByIdForAdmin($id);

        $payload = ['status' => $status];
        if ($status === 'completed') {
            $payload['completed_at'] = now();
            $payload['progress'] = 100;
        }
        if ($status === 'active') {
            $payload['completed_at'] = null;
        }

        $enrollment->update($payload);

        return $enrollment->fresh();
    }

    public function syncProgress(int $enrollmentId): Enrollment
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);

        $totalLessons = Lesson::whereHas('section', function ($query) use ($enrollment) {
            $query->where('course_id', $enrollment->course_id);
        })->count();

        $completedLessons = LessonProgress::where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')
            ->count();

        $progress = $totalLessons > 0
            ? (int) floor(($completedLessons / $totalLessons) * 100)
            : 0;

        $payload = ['progress' => $progress];
        if ($progress >= 100) {
            $payload['status'] = 'completed';
            $payload['completed_at'] = $enrollment->completed_at ?? now();
        } elseif ($enrollment->status === 'completed') {
            $payload['status'] = 'active';
            $payload['completed_at'] = null;
        }

        $enrollment->update($payload);

        return $enrollment->fresh();
    }
}
