<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Quiz\StoreQuizRequest;
use App\Http\Requests\Admin\Quiz\UpdateQuizRequest;
use App\Http\Resources\QuizResource;
use App\Services\QuizService;
use Illuminate\Http\Request;

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

    public function indexByCourse(string $courseId)
    {
        $quiz = $this->service->getByCourse((int) $courseId);

        return response()->json([
            'success' => true,
            'message' => 'Course quiz list retrieved successfully',
            'data' => QuizResource::collection($quiz),
        ]);
    }

    public function storeForSection(Request $request, string $courseId, string $sectionId)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'passing_score' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_random' => ['nullable', 'boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:0'],
            'open_at' => ['nullable', 'date'],
            'close_at' => ['nullable', 'date'],
        ]);

        $quiz = $this->service->createForCourseSection((int) $courseId, (int) $sectionId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Quiz created successfully',
            'data' => new QuizResource($quiz),
        ]);
    }

    public function updateForSection(Request $request, string $courseId, string $sectionId, string $quizId)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'passing_score' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_random' => ['nullable', 'boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:0'],
            'open_at' => ['nullable', 'date'],
            'close_at' => ['nullable', 'date'],
        ]);

        $quiz = $this->service->updateForCourseSection(
            (int) $courseId,
            (int) $sectionId,
            (int) $quizId,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz updated successfully',
            'data' => new QuizResource($quiz),
        ]);
    }

    public function show(string $id)
    {
        $quiz = $this->service->findByIdWithDetails((int) $id);

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
