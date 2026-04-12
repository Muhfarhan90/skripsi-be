<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
     * Menghapus ulasan siapapun (moderasi konten).
     */
    public function destroy(Request $request, string $courseId, string $reviewId)
    {
        $this->service->deleteReviewForAdmin((int) $courseId, (int) $reviewId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully by admin/instructor',
        ]);
    }
}
