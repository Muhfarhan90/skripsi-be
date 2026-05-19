<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $service;

    public function __construct(NotificationService $notificationService)
    {
        $this->service = $notificationService;
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $notifications = $this->service->getForUser((int) $request->user()->id, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Notification list retrieved successfully',
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $this->service->getUnreadCount((int) $request->user()->id),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = $this->service->markAsRead((int) $request->user()->id, (int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read successfully',
            'data' => new NotificationResource($notification),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $updatedCount = $this->service->markAllAsRead((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read successfully',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ]);
    }
}
