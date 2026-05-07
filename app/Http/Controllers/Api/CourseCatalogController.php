<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Services\CourseService;

class CourseCatalogController extends Controller
{
    protected CourseService $service;

    public function __construct(CourseService $courseService)
    {
        $this->service = $courseService;
    }

    public function index()
    {
        $courses = $this->service->getPublishedCatalog();

        return response()->json([
            'success' => true,
            'message' => 'Published courses retrieved successfully',
            'data' => CourseResource::collection($courses),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    public function show(string $slug)
    {
        $course = $this->service->findPublishedBySlug($slug);

        return response()->json([
            'success' => true,
            'message' => 'Course retrieved successfully',
            'data' => new CourseResource($course),
        ]);
    }
}
