<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuizAttempt\GradeQuizAnswerRequest;
use App\Http\Resources\QuizAnswerResource;
use App\Http\Resources\QuizAttemptResource;
use App\Services\QuizAttemptService;

class QuizAttemptController extends Controller
{
    protected QuizAttemptService $service;

    public function __construct(QuizAttemptService $quizAttemptService)
    {
        $this->service = $quizAttemptService;
    }

    public function index(string $quizId)
    {
        $attempts = $this->service->getAttemptsByQuizForAdmin((int) $quizId);

        return response()->json([
            'success' => true,
            'message' => 'Quiz attempt list retrieved successfully',
            'data' => QuizAttemptResource::collection($attempts),
            'meta' => [
                'current_page' => $attempts->currentPage(),
                'last_page' => $attempts->lastPage(),
                'per_page' => $attempts->perPage(),
                'total' => $attempts->total(),
            ],
        ]);
    }

    public function show(string $quizId, string $attemptId)
    {
        $attempt = $this->service->findAttemptForAdmin((int) $quizId, (int) $attemptId);

        return response()->json([
            'success' => true,
            'message' => 'Quiz attempt retrieved successfully',
            'data' => new QuizAttemptResource($attempt),
        ]);
    }

    public function gradeAnswer(GradeQuizAnswerRequest $request, string $quizId, string $attemptId, string $questionId)
    {
        $answer = $this->service->gradeAnswerForAdmin(
            (int) $quizId,
            (int) $attemptId,
            (int) $questionId,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz answer graded successfully',
            'data' => new QuizAnswerResource($answer),
        ]);
    }
}
