<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsiteSetting\UpsertFaqRequest;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Services\WebsiteSettingService;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        app(WebsiteSettingService::class)->ensureDefaultContent();

        $faqs = Faq::query()
            ->with('category')
            ->when($request->filled('faq_category_id'), fn ($query) => $query->where('faq_category_id', $request->integer('faq_category_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'FAQs retrieved successfully',
            'data' => FaqResource::collection($faqs),
        ]);
    }

    public function store(UpsertFaqRequest $request)
    {
        $faq = Faq::query()->create($this->normalizePayload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'FAQ created successfully',
            'data' => new FaqResource($faq->load('category')),
        ]);
    }

    public function show(Faq $faq)
    {
        return response()->json([
            'success' => true,
            'message' => 'FAQ retrieved successfully',
            'data' => new FaqResource($faq->load('category')),
        ]);
    }

    public function update(UpsertFaqRequest $request, Faq $faq)
    {
        $faq->update($this->normalizePayload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated successfully',
            'data' => new FaqResource($faq->fresh()->load('category')),
        ]);
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ deleted successfully',
        ]);
    }

    private function normalizePayload(array $payload): array
    {
        $payload['sort_order'] = max(1, (int) ($payload['sort_order'] ?? 1));
        $payload['is_active'] = $payload['is_active'] ?? true;

        return $payload;
    }
}
