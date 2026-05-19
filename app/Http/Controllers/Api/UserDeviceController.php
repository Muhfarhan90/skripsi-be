<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserDeviceRequest;
use App\Http\Resources\UserDeviceResource;
use App\Services\UserDeviceService;
use Illuminate\Http\Request;

class UserDeviceController extends Controller
{
    protected UserDeviceService $service;

    public function __construct(UserDeviceService $userDeviceService)
    {
        $this->service = $userDeviceService;
    }

    public function store(StoreUserDeviceRequest $request)
    {
        $device = $this->service->register($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User device registered successfully',
            'data' => new UserDeviceResource($device),
        ]);
    }

    public function destroy(Request $request, string $deviceId)
    {
        $device = $this->service->deactivateByDeviceId($request->user(), $deviceId);

        return response()->json([
            'success' => true,
            'message' => 'User device deactivated successfully',
            'data' => new UserDeviceResource($device),
        ]);
    }
}
