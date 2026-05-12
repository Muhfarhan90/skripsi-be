<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assignment\SubmitAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Http\Resources\AssignmentSubmissionResource;
use App\Services\AssignmentService;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    protected AssignmentService $service;
    protected EnrollmentService $enrollmentService;

    public function __construct(AssignmentService $assignmentService, EnrollmentService $enrollmentService)
    {
        $this->service = $assignmentService;
        $this->enrollmentService = $enrollmentService;
    }

    public function index(Request $request, string $enrollmentId)
    {
        $payload = $this->service->getAssignmentsForEnrollment((int) $request->user()->id, (int) $enrollmentId);

        return response()->json([
            'success' => true,
            'message' => 'Assignment list retrieved successfully',
            'data' => AssignmentResource::collection($payload['assignments']),
            'completion_requirement' => $payload['completion_requirement'],
        ]);
    }

    public function show(Request $request, string $enrollmentId, string $assignmentId)
    {
        $payload = $this->service->getAssignmentDetailForEnrollment(
            (int) $request->user()->id,
            (int) $enrollmentId,
            (int) $assignmentId
        );

        return response()->json([
            'success' => true,
            'message' => 'Assignment detail retrieved successfully',
            'data' => new AssignmentResource($payload['assignment']),
            'completion_requirement' => $payload['completion_requirement'],
        ]);
    }

    public function submit(SubmitAssignmentRequest $request, string $enrollmentId, string $assignmentId)
    {
        $submission = $this->service->submitForEnrollment(
            (int) $request->user()->id,
            (int) $enrollmentId,
            (int) $assignmentId,
            $request->validated()
        );

        $this->enrollmentService->syncProgress((int) $enrollmentId);

        return response()->json([
            'success' => true,
            'message' => 'Assignment submitted successfully',
            'data' => new AssignmentSubmissionResource($submission),
        ], 201);
    }
}
