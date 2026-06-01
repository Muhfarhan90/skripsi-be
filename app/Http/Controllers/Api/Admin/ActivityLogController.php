<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(
        protected ActivityLogService $service
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $event = trim((string) $request->query('event', ''));
        $activityLogs = $this->service->getAllForAdmin(
            $search,
            $perPage,
            $event !== '' ? $event : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Activity log list retrieved successfully',
            'data' => $activityLogs->getCollection()
                ->map(fn ($activity) => $this->service->mapActivity($activity))
                ->values(),
            'meta' => [
                'current_page' => $activityLogs->currentPage(),
                'last_page' => $activityLogs->lastPage(),
                'per_page' => $activityLogs->perPage(),
                'total' => $activityLogs->total(),
            ],
        ]);
    }
}
