<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonProgress\StoreLessonProgressRequest;
use App\Http\Resources\LessonProgressResource;
use App\Services\LessonProgressService;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    protected LessonProgressService $service;

    public function __construct(LessonProgressService $lessonProgressService)
    {
        $this->service = $lessonProgressService;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => ['required', 'exists:enrollments,id'],
        ]);

        $progress = $this->service->getByEnrollment(
            (int) $request->user()->id,
            (int) $validated['enrollment_id']
        );

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

    public function store(StoreLessonProgressRequest $request)
    {
        $progress = $this->service->upsert((int) $request->user()->id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lesson progress saved successfully',
            'data' => new LessonProgressResource($progress),
        ]);
    }

    public function show(Request $request, string $id)
    {
        $progress = $this->service->findByIdForUser((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Lesson progress retrieved successfully',
            'data' => new LessonProgressResource($progress),
        ]);
    }
}
