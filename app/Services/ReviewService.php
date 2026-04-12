<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /**
     * Mendapatkan semua review untuk sebuah course (Public).
     */
    public function getCourseReviews(int $courseId)
    {
        return Review::where('course_id', $courseId)
            ->with('user:id,fullname,avatar')
            ->latest()
            ->paginate(15);
    }

    /**
     * Student: Membuat ulasan untuk sebuah course.
     * Syarat: Harus enrolled dan statusnya completed.
     */
    public function createReview(int $courseId, User $user, array $data): Review
    {
        $enrollment = $this->ensureCanReview($courseId, $user->id);

        // Cek apakah sudah pernah review
        $existingReview = Review::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->first();

        if ($existingReview) {
            throw ValidationException::withMessages([
                'course_id' => ['You have already reviewed this course.'],
            ]);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'course_id' => $courseId,
            'enrollment_id' => $enrollment->id,
            'rating' => $data['rating'],
            'review' => $data['review'] ?? null,
        ]);

        return $review->load('user:id,fullname,avatar');
    }

    /**
     * Student: Memperbarui ulasan miliknya.
     */
    public function updateReview(int $courseId, int $reviewId, User $user, array $data): Review
    {
        $review = Review::where('course_id', $courseId)
            ->where('user_id', $user->id)
            ->findOrFail($reviewId);

        $review->update($data);

        return $review->fresh('user:id,fullname,avatar');
    }

    /**
     * Student: Menghapus ulasan miliknya sendiri.
     */
    public function deleteReview(int $courseId, int $reviewId, User $user): void
    {
        $review = Review::where('course_id', $courseId)
            ->where('user_id', $user->id)
            ->findOrFail($reviewId);

        $review->delete();
    }

    /**
     * Admin/Instructor: Menghapus ulasan siapapun.
     */
    public function deleteReviewForAdmin(int $courseId, int $reviewId, User $user): void
    {
        $this->ensureAdminAccess($courseId, $user);

        $review = Review::where('course_id', $courseId)->findOrFail($reviewId);
        $review->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    private function ensureCanReview(int $courseId, int $userId): Enrollment
    {
        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('status', 'completed')
            ->first();

        if (!$enrollment) {
            throw ValidationException::withMessages([
                'course_id' => ['You must complete this course before you can leave a review.'],
            ]);
        }

        return $enrollment;
    }

    private function ensureAdminAccess(int $courseId, User $user): void
    {
        $user->loadMissing('role');

        if ($user->role && $user->role->name === 'admin') {
            Course::findOrFail($courseId);
            return;
        }

        if ($user->role && $user->role->name === 'instructor') {
            $isTeaching = Course::where('id', $courseId)
                ->where('instructor_id', $user->id)
                ->exists();

            if (!$isTeaching) {
                throw ValidationException::withMessages([
                    'course_id' => ['You can only access reviews in courses you teach.'],
                ]);
            }
            return;
        }

        throw ValidationException::withMessages([
            'role' => ['You do not have permission to moderate this resource.'],
        ]);
    }
}
