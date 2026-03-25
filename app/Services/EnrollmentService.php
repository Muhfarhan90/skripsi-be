<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function getAllByUser(int $userId)
    {
        return Enrollment::where('user_id', $userId)
            ->latest()
            ->paginate(10);
    }

    public function findByIdForUser(int $userId, int $id): Enrollment
    {
        return Enrollment::where('user_id', $userId)
            ->findOrFail($id);
    }

    public function enrollmentStatus(int $userId, int $courseId): array
    {
        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (! $enrollment) {
            return [
                'is_enrolled' => false,
                'status' => 'not_enrolled',
                'progress' => 0,
            ];
        }

        return [
            'is_enrolled' => true,
            'enrollment_id' => $enrollment->id,
            'status' => $enrollment->status,
            'progress' => $enrollment->progress,
            'last_lesson_id' => $enrollment->last_lesson_id,
            'completed_at' => $enrollment->completed_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function create(int $userId, array $data): Enrollment
    {
        $course = Course::findOrFail((int) $data['course_id']);
        $transactionId = isset($data['transaction_id']) ? (int) $data['transaction_id'] : null;

        if ($course->status !== 'published') {
            throw ValidationException::withMessages([
                'course_id' => ['Course is not available for enrollment'],
            ]);
        }

        $existing = Enrollment::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $price = (float) ($course->discount_price > 0 ? $course->discount_price : $course->price);
        $requiresPayment = $price > 0;

        $transaction = null;
        if ($requiresPayment) {
            if (! $transactionId) {
                throw ValidationException::withMessages([
                    'transaction_id' => ['Paid course requires a valid transaction'],
                ]);
            }

            $transaction = Transaction::where('id', $transactionId)
                ->where('user_id', $userId)
                ->where('course_id', $course->id)
                ->first();

            if (! $transaction || $transaction->status !== 'paid') {
                throw ValidationException::withMessages([
                    'transaction_id' => ['Transaction is invalid or not paid'],
                ]);
            }
        } elseif ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)
                ->where('user_id', $userId)
                ->where('course_id', $course->id)
                ->first();

            if (! $transaction) {
                throw ValidationException::withMessages([
                    'transaction_id' => ['Transaction is invalid for this course'],
                ]);
            }
        }

        return DB::transaction(function () use ($userId, $course, $transaction) {
            $enrollment = Enrollment::create([
                'user_id' => $userId,
                'course_id' => $course->id,
                'transaction_id' => $transaction?->id,
                'progress' => 0,
                'status' => 'active',
            ]);

            $course->increment('total_students');

            return $enrollment;
        });
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
