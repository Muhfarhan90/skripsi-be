<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsiteSetting\UpsertWebsiteSocialLinkRequest;
use App\Http\Resources\WebsiteSocialLinkResource;
use App\Models\WebsiteSocialLink;
use Illuminate\Http\Request;

class WebsiteSocialLinkController extends Controller
{
    public function index(Request $request)
    {
        $links = WebsiteSocialLink::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Website social links retrieved successfully',
            'data' => WebsiteSocialLinkResource::collection($links),
        ]);
    }

    public function store(UpsertWebsiteSocialLinkRequest $request)
    {
        $link = WebsiteSocialLink::query()->create($this->normalizePayload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Website social link created successfully',
            'data' => new WebsiteSocialLinkResource($link),
        ]);
    }

    public function show(WebsiteSocialLink $websiteSocialLink)
    {
        return response()->json([
            'success' => true,
            'message' => 'Website social link retrieved successfully',
            'data' => new WebsiteSocialLinkResource($websiteSocialLink),
        ]);
    }

    public function update(UpsertWebsiteSocialLinkRequest $request, WebsiteSocialLink $websiteSocialLink)
    {
        $websiteSocialLink->update($this->normalizePayload($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Website social link updated successfully',
            'data' => new WebsiteSocialLinkResource($websiteSocialLink->fresh()),
        ]);
    }

    public function destroy(WebsiteSocialLink $websiteSocialLink)
    {
        $websiteSocialLink->delete();

        return response()->json([
            'success' => true,
            'message' => 'Website social link deleted successfully',
        ]);
    }

    private function normalizePayload(array $payload): array
    {
        $payload['platform'] = $payload['platform'] ?? 'custom';
        $payload['sort_order'] = $payload['sort_order'] ?? 1;
        $payload['is_active'] = $payload['is_active'] ?? true;

        return $payload;
    }
}
