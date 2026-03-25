<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
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

    public function store(StoreEnrollmentRequest $request)
    {
        $enrollment = $this->service->create((int) $request->user()->id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Enrollment processed successfully',
            'data' => new EnrollmentResource($enrollment),
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

    public function status(Request $request, string $courseId)
    {
        $status = $this->service->enrollmentStatus((int) $request->user()->id, (int) $courseId);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment status retrieved successfully',
            'data' => $status,
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
}
