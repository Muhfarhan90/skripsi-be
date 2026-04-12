<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    protected ReviewService $service;

    public function __construct(ReviewService $reviewService)
    {
        $this->service = $reviewService;
    }

    /**
     * Mendapatkan daftar ulasan sebuah kursus (Bisa diakses publik / semua role).
     */
    public function index(string $courseId)
    {
        $reviews = $this->service->getCourseReviews((int) $courseId);

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved successfully',
            'data' => ReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Membuat ulasan baru (Student yang sudah completed).
     */
    public function store(StoreReviewRequest $request, string $courseId)
    {
        $review = $this->service->createReview(
            (int) $courseId,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'data' => new ReviewResource($review),
        ], 201);
    }

    /**
     * Memperbarui ulasan milik sendiri.
     */
    public function update(UpdateReviewRequest $request, string $courseId, string $reviewId)
    {
        $review = $this->service->updateReview(
            (int) $courseId,
            (int) $reviewId,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully',
            'data' => new ReviewResource($review),
        ]);
    }

    /**
     * Menghapus ulasan milik sendiri.
     */
    public function destroy(Request $request, string $courseId, string $reviewId)
    {
        $this->service->deleteReview((int) $courseId, (int) $reviewId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully',
        ]);
    }
}
