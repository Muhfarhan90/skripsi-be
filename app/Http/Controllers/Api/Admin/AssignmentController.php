<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assignment\ReviewAssignmentSubmissionRequest;
use App\Http\Requests\Admin\Assignment\StoreAssignmentRequest;
use App\Http\Requests\Admin\Assignment\UpdateAssignmentRequest;
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

    public function indexByCourse(Request $request, string $courseId)
    {
        $assignments = $this->service->getAssignmentsByCourseForAdmin((int) $courseId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Course assignment list retrieved successfully',
            'data' => AssignmentResource::collection($assignments),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
        ]);
    }

    public function storeForCourse(StoreAssignmentRequest $request, string $courseId)
    {
        $assignment = $this->service->createForCourse(
            (int) $courseId,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Assignment created successfully',
            'data' => new AssignmentResource($assignment),
        ], 201);
    }

    public function updateForCourse(UpdateAssignmentRequest $request, string $courseId, string $assignmentId)
    {
        $assignment = $this->service->updateForCourse(
            (int) $courseId,
            (int) $assignmentId,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated successfully',
            'data' => new AssignmentResource($assignment),
        ]);
    }

    public function submissions(Request $request, string $assignmentId)
    {
        $submissions = $this->service->getSubmissionsForAssignmentForAdmin((int) $assignmentId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Assignment submission list retrieved successfully',
            'data' => AssignmentSubmissionResource::collection($submissions),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    public function reviewSubmission(ReviewAssignmentSubmissionRequest $request, string $submissionId)
    {
        $submission = $this->service->reviewSubmissionForAdmin(
            (int) $submissionId,
            $request->user(),
            $request->validated()
        );

        if ($submission->enrollment_id) {
            $this->enrollmentService->syncProgress((int) $submission->enrollment_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment submission reviewed successfully',
            'data' => new AssignmentSubmissionResource($submission),
        ]);
    }
}
