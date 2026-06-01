<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsiteSetting\UpsertWebsiteSectionRequest;
use App\Http\Resources\WebsiteSectionResource;
use App\Models\WebsiteSection;
use App\Services\WebsiteSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteSectionController extends Controller
{
    public function index(Request $request)
    {
        app(WebsiteSettingService::class)->ensureDefaultContent();

        $sections = WebsiteSection::query()
            ->with('items')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Website sections retrieved successfully',
            'data' => WebsiteSectionResource::collection($sections),
        ]);
    }

    public function store(UpsertWebsiteSectionRequest $request)
    {
        $section = DB::transaction(fn () => $this->saveSection(new WebsiteSection(), $request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Website section created successfully',
            'data' => new WebsiteSectionResource($section->load('items')),
        ]);
    }

    public function show(WebsiteSection $websiteSection)
    {
        return response()->json([
            'success' => true,
            'message' => 'Website section retrieved successfully',
            'data' => new WebsiteSectionResource($websiteSection->load('items')),
        ]);
    }

    public function update(UpsertWebsiteSectionRequest $request, WebsiteSection $websiteSection)
    {
        $section = DB::transaction(fn () => $this->saveSection($websiteSection, $request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Website section updated successfully',
            'data' => new WebsiteSectionResource($section->load('items')),
        ]);
    }

    public function destroy(WebsiteSection $websiteSection)
    {
        $websiteSection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Website section deleted successfully',
        ]);
    }

    private function saveSection(WebsiteSection $section, array $payload): WebsiteSection
    {
        $items = $payload['items'] ?? null;
        unset($payload['items']);

        $section->fill($payload)->save();

        if (is_array($items)) {
            $section->items()->delete();

            foreach ($items as $item) {
                $section->items()->create($item);
            }
        }

        return $section->fresh('items');
    }
}
