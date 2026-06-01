<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $service
    ) {
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Admin dashboard retrieved successfully',
            'data' => $this->service->getAdminDashboard(),
        ]);
    }
}
