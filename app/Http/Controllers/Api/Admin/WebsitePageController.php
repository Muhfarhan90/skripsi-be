<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsiteSetting\UpsertWebsitePageRequest;
use App\Http\Resources\WebsitePageResource;
use App\Models\WebsitePage;
use App\Services\WebsiteSettingService;
use Illuminate\Http\Request;

class WebsitePageController extends Controller
{
    public function index(Request $request)
    {
        app(WebsiteSettingService::class)->ensureDefaultContent();

        $pages = WebsitePage::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('slug')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Website pages retrieved successfully',
            'data' => WebsitePageResource::collection($pages),
        ]);
    }

    public function store(UpsertWebsitePageRequest $request)
    {
        $page = WebsitePage::query()->create($this->normalizePayload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Website page created successfully',
            'data' => new WebsitePageResource($page),
        ]);
    }

    public function show(WebsitePage $websitePage)
    {
        return response()->json([
            'success' => true,
            'message' => 'Website page retrieved successfully',
            'data' => new WebsitePageResource($websitePage),
        ]);
    }

    public function update(UpsertWebsitePageRequest $request, WebsitePage $websitePage)
    {
        $websitePage->update($this->normalizePayload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Website page updated successfully',
            'data' => new WebsitePageResource($websitePage->fresh()),
        ]);
    }

    public function destroy(WebsitePage $websitePage)
    {
        $websitePage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Website page deleted successfully',
        ]);
    }

    private function normalizePayload(array $payload): array
    {
        if (($payload['status'] ?? null) === 'published' && empty($payload['published_at'])) {
            $payload['published_at'] = now();
        }

        if (($payload['status'] ?? null) === 'draft') {
            $payload['published_at'] = null;
        }

        return $payload;
    }
}
