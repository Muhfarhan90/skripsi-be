<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Section\StoreSectionRequest;
use App\Http\Requests\Admin\Section\UpdateSectionRequest;
use App\Http\Resources\SectionResource;
use App\Services\SectionService;

class SectionController extends Controller
{
    protected SectionService $service;

    public function __construct(SectionService $sectionService)
    {
        $this->service = $sectionService;
    }

    public function index()
    {
        $section = $this->service->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Section list retrieved successfully',
            'data' => SectionResource::collection($section),
            'meta' => [
                'current_page' => $section->currentPage(),
                'last_page' => $section->lastPage(),
                'per_page' => $section->perPage(),
                'total' => $section->total(),
            ],
        ]);
    }

    public function store(StoreSectionRequest $request)
    {
        $section = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Section created successfully',
            'data' => new SectionResource($section),
        ]);
    }

    public function show(string $id)
    {
        $section = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Section retrieved successfully',
            'data' => new SectionResource($section),
        ]);
    }

    public function update(UpdateSectionRequest $request, string $id)
    {
        $section = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Section updated successfully',
            'data' => new SectionResource($section),
        ]);
    }

    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Section deleted successfully',
        ]);
    }
}