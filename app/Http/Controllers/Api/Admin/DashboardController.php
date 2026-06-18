<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $service
    ) {
    }

    public function index(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json([
            'success' => true,
            'message' => 'Admin dashboard retrieved successfully',
            'data' => $this->service->getAdminDashboard($startDate, $endDate),
        ]);
    }
}
