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
}
