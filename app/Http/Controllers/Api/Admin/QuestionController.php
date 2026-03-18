<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Question\StoreQuestionRequest;
use App\Http\Requests\Admin\Question\UpdateQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Services\QuestionService;

class QuestionController extends Controller
{
    protected QuestionService $service;

    public function __construct(QuestionService $questionService)
    {
        $this->service = $questionService;
    }

    public function index()
    {
        $question = $this->service->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Question list retrieved successfully',
            'data' => QuestionResource::collection($question),
            'meta' => [
                'current_page' => $question->currentPage(),
                'last_page' => $question->lastPage(),
                'per_page' => $question->perPage(),
                'total' => $question->total(),
            ],
        ]);
    }

    public function store(StoreQuestionRequest $request)
    {
        $question = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Question created successfully',
            'data' => new QuestionResource($question),
        ]);
    }

    public function show(string $id)
    {
        $question = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Question retrieved successfully',
            'data' => new QuestionResource($question),
        ]);
    }

    public function update(UpdateQuestionRequest $request, string $id)
    {
        $question = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Question updated successfully',
            'data' => new QuestionResource($question),
        ]);
    }

    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Question deleted successfully',
        ]);
    }
}