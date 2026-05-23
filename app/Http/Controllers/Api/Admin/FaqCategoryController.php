<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsiteSetting\UpsertFaqCategoryRequest;
use App\Http\Resources\FaqCategoryResource;
use App\Models\FaqCategory;
use App\Services\WebsiteSettingService;
use Illuminate\Http\Request;

class FaqCategoryController extends Controller
{
    public function index(Request $request)
    {
        app(WebsiteSettingService::class)->ensureDefaultContent();

        $categories = FaqCategory::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'FAQ categories retrieved successfully',
            'data' => FaqCategoryResource::collection($categories),
        ]);
    }

    public function store(UpsertFaqCategoryRequest $request)
    {
        $category = FaqCategory::query()->create($this->normalizePayload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'FAQ category created successfully',
            'data' => new FaqCategoryResource($category),
        ]);
    }

    public function show(FaqCategory $faqCategory)
    {
        return response()->json([
            'success' => true,
            'message' => 'FAQ category retrieved successfully',
            'data' => new FaqCategoryResource($faqCategory),
        ]);
    }

    public function update(UpsertFaqCategoryRequest $request, FaqCategory $faqCategory)
    {
        $faqCategory->update($this->normalizePayload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'FAQ category updated successfully',
            'data' => new FaqCategoryResource($faqCategory->fresh()),
        ]);
    }

    public function destroy(FaqCategory $faqCategory)
    {
        $faqCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ category deleted successfully',
        ]);
    }

    private function normalizePayload(array $payload): array
    {
        $payload['is_active'] = $payload['is_active'] ?? true;

        return $payload;
    }
}
