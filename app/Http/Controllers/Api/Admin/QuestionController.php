<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Question\StoreQuestionRequest;
use App\Http\Requests\Admin\Question\UpdateQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Services\QuestionService;
use Illuminate\Http\Request;

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

    public function storeForQuiz(Request $request, string $quizId)
    {
        $validated = $request->validate([
            'question_text' => ['required', 'string'],
            'image_url' => ['nullable', 'string'],
            'type' => ['required', 'in:multiple_choice,true_false'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $question = $this->service->createForQuiz((int) $quizId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Question created successfully',
            'data' => new QuestionResource($question->load('options')),
        ]);
    }

    public function updateForQuiz(Request $request, string $quizId, string $questionId)
    {
        $validated = $request->validate([
            'question_text' => ['sometimes', 'string'],
            'image_url' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:multiple_choice,true_false'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $question = $this->service->updateForQuiz((int) $quizId, (int) $questionId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Question updated successfully',
            'data' => new QuestionResource($question->load('options')),
        ]);
    }

    public function destroyForQuiz(string $quizId, string $questionId)
    {
        $this->service->deleteForQuiz((int) $quizId, (int) $questionId);

        return response()->json([
            'success' => true,
            'message' => 'Question deleted successfully',
        ]);
    }

    public function reorderForQuiz(Request $request, string $quizId)
    {
        $validated = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $this->service->reorderForQuiz((int) $quizId, $validated['question_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Question order updated successfully',
        ]);
    }
}
