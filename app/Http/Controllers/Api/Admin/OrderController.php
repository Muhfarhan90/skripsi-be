<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $service;

    public function __construct(OrderService $orderService)
    {
        $this->service = $orderService;
    }

    public function index()
    {
        $orders = $this->service->getAllForAdmin();
        
        return response()->json([
            'success' => true,
            'message' => 'Order list retrieved successfully',
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            $order = $this->service->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Order and Enrollment created successfully by Admin',
                'data' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $id)
    {
        $order = $this->service->findByIdForAdmin((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Order details retrieved successfully',
            'data' => new OrderResource($order),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $order = $this->service->update((int) $id, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => new OrderResource($order),
        ]);
    }

    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|string|in:pending,completed,cancelled',
        ]);

        $order = $this->service->updateStatus((int) $id, $request->status);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => new OrderResource($order),
        ]);
    }
}
