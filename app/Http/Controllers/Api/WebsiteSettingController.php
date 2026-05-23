<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqCategoryResource;
use App\Http\Resources\FaqResource;
use App\Http\Resources\WebsitePageResource;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Services\WebsiteSettingService;

class WebsiteSettingController extends Controller
{
    public function __construct(
        protected WebsiteSettingService $websiteSettingService
    ) {}

    public function show()
    {
        return response()->json([
            'success' => true,
            'message' => 'Website settings retrieved successfully',
            'data' => $this->websiteSettingService->getHomePayload(),
        ]);
    }

    public function home()
    {
        return response()->json([
            'success' => true,
            'message' => 'Website home content retrieved successfully',
            'data' => $this->websiteSettingService->getHomePayload(),
        ]);
    }

    public function page(string $slug)
    {
        return response()->json([
            'success' => true,
            'message' => 'Website page retrieved successfully',
            'data' => new WebsitePageResource($this->websiteSettingService->getPublishedPage($slug)),
        ]);
    }

    public function faqs()
    {
        $this->websiteSettingService->ensureDefaultContent();

        $faqs = Faq::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'FAQs retrieved successfully',
            'data' => FaqResource::collection($faqs),
        ]);
    }

    public function faqCategories()
    {
        $this->websiteSettingService->ensureDefaultContent();

        $categories = FaqCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'FAQ categories retrieved successfully',
            'data' => FaqCategoryResource::collection($categories),
        ]);
    }
}
