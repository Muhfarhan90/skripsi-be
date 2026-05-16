<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\StoreCourseRequest;
use App\Http\Requests\Admin\Course\UpsertCourseCurriculumRequest;
use App\Http\Requests\Admin\Course\UpdateCourseRequest;
use App\Http\Resources\CourseCurriculumResource;
use App\Http\Resources\CourseResource;
use App\Services\CourseService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    protected CourseService $service;

    public function __construct(CourseService $courseService)
    {
        $this->service = $courseService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $search = trim((string) $request->query('search', ''));
        $course = $this->service->getAll($perPage, $search);
        return response()->json([
            'success' => true,
            'message' => 'Courses retrieved successfully',
            'data' => CourseResource::collection($course),
            'meta' => [
                'current_page' => $course->currentPage(),
                'last_page' => $course->lastPage(),
                'per_page' => $course->perPage(),
                'total' => $course->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request)
    {
        $course = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully',
            'data' => new CourseResource($course),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $course = $this->service->findById($id);

        return response()->json([
            'success' => true,
            'message' => 'Course retrieved successfully',
            'data' => new CourseResource($course),
        ]);
    }

    public function curriculum(string $courseId)
    {
        $course = $this->service->findByIdWithCurriculum((int) $courseId);

        return response()->json([
            'success' => true,
            'message' => 'Course curriculum retrieved successfully',
            'data' => new CourseCurriculumResource($course),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, string $id)
    {
        $course = $this->service->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully',
            'data' => new CourseResource($course),
        ]);
    }

    public function upsertCurriculum(UpsertCourseCurriculumRequest $request, string $courseId)
    {
        $course = $this->service->upsertCurriculum((int) $courseId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Course curriculum updated successfully',
            'data' => new CourseCurriculumResource($course),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->service->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully',
        ]);
    }
}
