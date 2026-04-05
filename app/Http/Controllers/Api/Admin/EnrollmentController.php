<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Enrollment\UpdateEnrollmentStatusRequest;
use App\Http\Resources\EnrollmentResource;
use App\Services\EnrollmentService;

class EnrollmentController extends Controller
{
    protected EnrollmentService $service;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->service = $enrollmentService;
    }

    public function index()
    {
        $enrollments = $this->service->getAllForAdmin();

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

    public function show(string $id)
    {
        $enrollment = $this->service->findByIdForAdmin((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment retrieved successfully',
            'data' => new EnrollmentResource($enrollment),
        ]);
    }

    public function byCourse(string $courseId)
    {
        $enrollments = $this->service->getByCourseIdForAdmin((int) $courseId);

        return response()->json([
            'success' => true,
            'message' => 'Course enrollment list retrieved successfully',
            'data' => EnrollmentResource::collection($enrollments),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function updateStatus(UpdateEnrollmentStatusRequest $request, string $id)
    {
        $enrollment = $this->service->updateStatusForAdmin((int) $id, $request->validated()['status']);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment status updated successfully',
            'data' => new EnrollmentResource($enrollment),
        ]);
    }

    public function syncProgress(string $id)
    {
        $enrollment = $this->service->syncProgress((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment progress synced successfully',
            'data' => new EnrollmentResource($enrollment),
        ]);
    }
}
