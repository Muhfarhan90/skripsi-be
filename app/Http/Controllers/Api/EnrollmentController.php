<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\LessonResource;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    protected EnrollmentService $service;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->service = $enrollmentService;
    }

    public function index(Request $request)
    {
        $enrollments = $this->service->getAllByUser((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment list retrieved successfully',
            'data' => EnrollmentResource::collection($enrollments),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $enrollment = $this->service->findByIdForUser((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment retrieved successfully',
            'data' => new EnrollmentResource($enrollment),
        ]);
    }

    public function complete(Request $request, string $id)
    {
        $enrollment = $this->service->complete((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment completed successfully',
            'data' => new EnrollmentResource($enrollment),
        ]);
    }

    public function progressSummary(Request $request, string $id)
    {
        $summary = $this->service->progressSummary((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment progress summary retrieved successfully',
            'data' => $summary,
        ]);
    }

    public function nextLesson(Request $request, string $id)
    {
        $lesson = $this->service->nextLesson((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => $lesson ? 'Next lesson retrieved successfully' : 'No next lesson available',
            'data' => $lesson ? new LessonResource($lesson) : null,
        ]);
    }
}
