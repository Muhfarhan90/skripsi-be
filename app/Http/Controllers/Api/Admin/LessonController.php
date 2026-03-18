<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lesson\StoreLessonRequest;
use App\Http\Requests\Admin\Lesson\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Services\LessonService;

class LessonController extends Controller
{
    protected LessonService $service;

    public function __construct(LessonService $lessonService)
    {
        $this->service = $lessonService;
    }

    public function index()
    {
        $lesson = $this->service->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Lesson list retrieved successfully',
            'data' => LessonResource::collection($lesson),
            'meta' => [
                'current_page' => $lesson->currentPage(),
                'last_page' => $lesson->lastPage(),
                'per_page' => $lesson->perPage(),
                'total' => $lesson->total(),
            ],
        ]);
    }

    public function store(StoreLessonRequest $request)
    {
        $lesson = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lesson created successfully',
            'data' => new LessonResource($lesson),
        ]);
    }

    public function show(string $id)
    {
        $lesson = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Lesson retrieved successfully',
            'data' => new LessonResource($lesson),
        ]);
    }

    public function update(UpdateLessonRequest $request, string $id)
    {
        $lesson = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lesson updated successfully',
            'data' => new LessonResource($lesson),
        ]);
    }

    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Lesson deleted successfully',
        ]);
    }
}