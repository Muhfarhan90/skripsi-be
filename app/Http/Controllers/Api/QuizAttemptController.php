<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuizAttempt\UpsertQuizAnswerRequest;
use App\Http\Resources\QuizAnswerResource;
use App\Http\Resources\QuizAttemptResource;
use App\Services\QuizAttemptService;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    protected QuizAttemptService $service;

    public function __construct(QuizAttemptService $quizAttemptService)
    {
        $this->service = $quizAttemptService;
    }

    public function index(Request $request, string $enrollmentId, string $quizId)
    {
        $attempts = $this->service->getAttemptsByQuizForUser(
            (int) $request->user()->id,
            (int) $enrollmentId,
            (int) $quizId
        );

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

    public function store(Request $request, string $enrollmentId, string $quizId)
    {
        $attempt = $this->service->startAttemptForUser(
            (int) $request->user()->id,
            (int) $enrollmentId,
            (int) $quizId
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz attempt started successfully',
            'data' => new QuizAttemptResource($attempt),
        ]);
    }

    public function show(Request $request, string $enrollmentId, string $quizId, string $attemptId)
    {
        $attempt = $this->service->findAttemptForUser(
            (int) $request->user()->id,
            (int) $enrollmentId,
            (int) $quizId,
            (int) $attemptId
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz attempt retrieved successfully',
            'data' => new QuizAttemptResource($attempt),
        ]);
    }

    public function upsertAnswer(
        UpsertQuizAnswerRequest $request,
        string $enrollmentId,
        string $quizId,
        string $attemptId,
        string $questionId
    ) {
        $answer = $this->service->upsertAnswerForUser(
            (int) $request->user()->id,
            (int) $enrollmentId,
            (int) $quizId,
            (int) $attemptId,
            (int) $questionId,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz answer saved successfully',
            'data' => new QuizAnswerResource($answer),
        ]);
    }

    public function submit(Request $request, string $enrollmentId, string $quizId, string $attemptId)
    {
        $attempt = $this->service->submitAttemptForUser(
            (int) $request->user()->id,
            (int) $enrollmentId,
            (int) $quizId,
            (int) $attemptId
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz attempt submitted successfully',
            'data' => new QuizAttemptResource($attempt),
        ]);
    }
}
