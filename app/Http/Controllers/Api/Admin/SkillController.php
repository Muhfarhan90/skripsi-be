<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Skill\StoreSkillRequest;
use App\Http\Requests\Admin\Skill\UpdateSkillRequest;
use App\Http\Resources\SkillResource;
use App\Services\SkillService;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function __construct(
        protected SkillService $service
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $search = trim((string) $request->query('search', ''));
        $skills = $this->service->getAll($perPage, $search);

        return response()->json([
            'success' => true,
            'message' => 'Skills retrieved successfully',
            'data' => SkillResource::collection($skills),
            'meta' => [
                'current_page' => $skills->currentPage(),
                'last_page' => $skills->lastPage(),
                'per_page' => $skills->perPage(),
                'total' => $skills->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSkillRequest $request)
    {
        $skill = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Skill created successfully',
            'data' => new SkillResource($skill),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $skill = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Skill retrieved successfully',
            'data' => new SkillResource($skill),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSkillRequest $request, string $id)
    {
        $skill = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Skill updated successfully',
            'data' => new SkillResource($skill),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Skill deleted successfully',
        ]);
    }
}
