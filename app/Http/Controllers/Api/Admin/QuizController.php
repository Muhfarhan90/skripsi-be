<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Quiz\StoreQuizRequest;
use App\Http\Requests\Admin\Quiz\UpdateQuizRequest;
use App\Http\Resources\QuizResource;
use App\Services\QuizService;

class QuizController extends Controller
{
    protected QuizService $service;

    public function __construct(QuizService $quizService)
    {
        $this->service = $quizService;
    }

    public function index()
    {
        $quiz = $this->service->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Quiz list retrieved successfully',
            'data' => QuizResource::collection($quiz),
            'meta' => [
                'current_page' => $quiz->currentPage(),
                'last_page' => $quiz->lastPage(),
                'per_page' => $quiz->perPage(),
                'total' => $quiz->total(),
            ],
        ]);
    }

    public function store(StoreQuizRequest $request)
    {
        $quiz = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Quiz created successfully',
            'data' => new QuizResource($quiz),
        ]);
    }

    public function show(string $id)
    {
        $quiz = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Quiz retrieved successfully',
            'data' => new QuizResource($quiz),
        ]);
    }

    public function update(UpdateQuizRequest $request, string $id)
    {
        $quiz = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Quiz updated successfully',
            'data' => new QuizResource($quiz),
        ]);
    }

    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Quiz deleted successfully',
        ]);
    }
}