<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Option\StoreOptionRequest;
use App\Http\Requests\Admin\Option\UpdateOptionRequest;
use App\Http\Resources\OptionResource;
use App\Services\OptionService;
use Illuminate\Http\Request;

class OptionController extends Controller
{
    protected OptionService $service;

    public function __construct(OptionService $optionService)
    {
        $this->service = $optionService;
    }

    public function index()
    {
        $option = $this->service->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Option list retrieved successfully',
            'data' => OptionResource::collection($option),
            'meta' => [
                'current_page' => $option->currentPage(),
                'last_page' => $option->lastPage(),
                'per_page' => $option->perPage(),
                'total' => $option->total(),
            ],
        ]);
    }

    public function store(StoreOptionRequest $request)
    {
        $option = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Option created successfully',
            'data' => new OptionResource($option),
        ]);
    }

    public function show(string $id)
    {
        $option = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Option retrieved successfully',
            'data' => new OptionResource($option),
        ]);
    }

    public function update(UpdateOptionRequest $request, string $id)
    {
        $option = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Option updated successfully',
            'data' => new OptionResource($option),
        ]);
    }

    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Option deleted successfully',
        ]);
    }

    public function storeForQuestion(Request $request, string $questionId)
    {
        $validated = $request->validate([
            'option_text' => ['required', 'string'],
            'image_url' => ['nullable', 'string'],
            'is_correct' => ['required', 'boolean'],
        ]);

        $option = $this->service->createForQuestion((int) $questionId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Option created successfully',
            'data' => new OptionResource($option),
        ]);
    }

    public function updateForQuestion(Request $request, string $questionId, string $optionId)
    {
        $validated = $request->validate([
            'option_text' => ['sometimes', 'string'],
            'image_url' => ['nullable', 'string'],
            'is_correct' => ['sometimes', 'boolean'],
        ]);

        $option = $this->service->updateForQuestion((int) $questionId, (int) $optionId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Option updated successfully',
            'data' => new OptionResource($option),
        ]);
    }

    public function destroyForQuestion(string $questionId, string $optionId)
    {
        $this->service->deleteForQuestion((int) $questionId, (int) $optionId);

        return response()->json([
            'success' => true,
            'message' => 'Option deleted successfully',
        ]);
    }
}
