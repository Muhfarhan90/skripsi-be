<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseCurriculumResource;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\LessonProgressResource;
use App\Http\Resources\StudentQuizDetailResource;
use App\Models\Course;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    protected EnrollmentService $service;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->service = $enrollmentService;
    }

    public function index(Request $request)
    {
        $enrollments = $this->service->getAllByUser((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment list retrieved successfully',
            'data' => EnrollmentResource::collection($enrollments),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $enrollment = $this->service->findByIdForUser((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment retrieved successfully',
            'data' => new EnrollmentResource($enrollment),
        ]);
    }

    public function curriculum(Request $request, string $id)
    {
        $enrollment = $this->service->findByIdForUser((int) $request->user()->id, (int) $id);
        $this->service->assertCanReadMaterial($enrollment);
        $enrollment->loadMissing('courseOffering');
        $courseId = $enrollment->courseOffering?->course_id;
        if (! $courseId) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Enrollment is missing a valid course offering reference.'],
            ]);
        }

        $course = Course::query()
            ->with([
                'category:id,name',
                'instructor:id,fullname',
                'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.lessons' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.quizzes' => fn ($query) => $query->orderByDesc('id'),
                'sections.assignments' => fn ($query) => $query
                    ->where('status', 'published')
                    ->orderBy('due_at')
                    ->orderBy('id'),
            ])
            ->findOrFail($courseId);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment curriculum retrieved successfully',
            'data' => new CourseCurriculumResource($course),
        ]);
    }

    public function lessonDetail(Request $request, string $id, string $lessonId)
    {
        $detail = $this->service->findLessonDetailForUser(
            (int) $request->user()->id,
            (int) $id,
            (int) $lessonId
        );

        $lesson = $detail['lesson'];
        $section = $lesson->section;

        return response()->json([
            'success' => true,
            'message' => 'Enrollment lesson detail retrieved successfully',
            'data' => [
                'enrollment_id' => $detail['enrollment']->id,
                'section' => $section ? [
                    'id' => $section->id,
                    'course_id' => $section->course_id,
                    'title' => $section->title,
                    'sort_order' => $section->sort_order,
                ] : null,
                'lesson' => new LessonResource($lesson),
                'progress' => $detail['progress']
                    ? new LessonProgressResource($detail['progress'])
                    : null,
            ],
        ]);
    }

    public function quizDetail(Request $request, string $id, string $quizId)
    {
        $detail = $this->service->findQuizDetailForUser(
            (int) $request->user()->id,
            (int) $id,
            (int) $quizId
        );

        return response()->json([
            'success' => true,
            'message' => 'Enrollment quiz detail retrieved successfully',
            'data' => new StudentQuizDetailResource(
                $detail['quiz'],
                $detail['unsupported_question_types']
            ),
        ]);
    }

    public function complete(Request $request, string $id)
    {
        $enrollment = $this->service->complete((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment completed successfully',
            'data' => new EnrollmentResource($enrollment),
        ]);
    }

    public function progressSummary(Request $request, string $id)
    {
        $summary = $this->service->progressSummary((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment progress summary retrieved successfully',
            'data' => $summary,
        ]);
    }

    public function nextLesson(Request $request, string $id)
    {
        $lesson = $this->service->nextLesson((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => $lesson ? 'Next lesson retrieved successfully' : 'No next lesson available',
            'data' => $lesson ? new LessonResource($lesson) : null,
        ]);
    }
}
