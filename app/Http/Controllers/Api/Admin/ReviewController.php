<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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

    public function index(Request $request, string $courseId)
    {
        $reviews = $this->service->getCourseReviewsForAdmin((int) $courseId, $request->user());

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
     * Menghapus ulasan siapapun (moderasi konten).
     */
    public function destroy(Request $request, string $courseId, string $reviewId)
    {
        $this->service->deleteReviewForAdmin((int) $courseId, (int) $reviewId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully by admin',
        ]);
    }
}
