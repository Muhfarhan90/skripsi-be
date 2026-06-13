<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsiteSetting\UpdateWebsiteSettingRequest;
use App\Http\Resources\WebsiteSettingResource;
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
            'data' => new WebsiteSettingResource($this->websiteSettingService->getSettings()),
        ]);
    }

    public function home()
    {
        return response()->json([
            'success' => true,
            'message' => 'Website home content retrieved successfully',
            'data' => $this->websiteSettingService->getHomePayload(true),
        ]);
    }

    public function update(UpdateWebsiteSettingRequest $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Website settings updated successfully',
            'data' => new WebsiteSettingResource(
                $this->websiteSettingService->updateSettings($request->validated())
            ),
        ]);
    }

    public function uploadAsset(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'type' => ['required', 'string', 'max:50'],
        ]);

        $path = $this->websiteSettingService->uploadWebsiteAsset(
            $request->file('file'),
            (string) $request->input('type')
        );

        return response()->json([
            'success' => true,
            'message' => 'Website asset uploaded successfully',
            'data' => [
                'path' => $path,
            ],
        ]);
    }
}
