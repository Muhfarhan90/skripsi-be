<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonProgress\UpsertLessonProgressRequest;
use App\Http\Resources\LessonProgressResource;
use App\Services\LessonProgressService;

class LessonProgressController extends Controller
{
    protected LessonProgressService $service;

    public function __construct(LessonProgressService $lessonProgressService)
    {
        $this->service = $lessonProgressService;
    }

    public function index(string $enrollmentId)
    {
        $progress = $this->service->getByEnrollmentForAdmin((int) $enrollmentId);

        return response()->json([
            'success' => true,
            'message' => 'Lesson progress list retrieved successfully',
            'data' => LessonProgressResource::collection($progress),
            'meta' => [
                'current_page' => $progress->currentPage(),
                'last_page' => $progress->lastPage(),
                'per_page' => $progress->perPage(),
                'total' => $progress->total(),
            ],
        ]);
    }

    public function show(string $enrollmentId, string $lessonId)
    {
        $progress = $this->service->findByEnrollmentAndLessonForAdmin((int) $enrollmentId, (int) $lessonId);

        return response()->json([
            'success' => true,
            'message' => 'Lesson progress retrieved successfully',
            'data' => new LessonProgressResource($progress),
        ]);
    }

    public function upsert(UpsertLessonProgressRequest $request, string $enrollmentId, string $lessonId)
    {
        $progress = $this->service->upsertForAdmin(
            (int) $enrollmentId,
            (int) $lessonId,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Lesson progress saved successfully',
            'data' => new LessonProgressResource($progress),
        ]);
    }
}
