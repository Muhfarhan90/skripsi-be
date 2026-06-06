<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\SubmitPaymentRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UploadPaymentProofRequest;
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

    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:pending,completed,cancelled'],
        ]);

        $orders = $this->service->getAllForStudent(
            (int) $request->user()->id,
            (int) ($validated['per_page'] ?? 10),
            $validated['status'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Order history retrieved successfully',
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
                'message' => 'Order created successfully. Please complete the payment.',
                'data' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function uploadPaymentProof(UploadPaymentProofRequest $request)
    {
        $path = $this->service->uploadPaymentProof($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Payment proof uploaded successfully',
            'data' => [
                'path' => $path,
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        try {
            $order = $this->service->findByIdForStudent((int)$id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Order details retrieved successfully',
                'data' => new OrderResource($order),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or access denied',
            ], 404);
        }
    }

    public function submitPayment(SubmitPaymentRequest $request, string $id)
    {
        try {
            $order = $this->service->submitPaymentByStudent(
                (int) $request->user()->id,
                (int) $id,
                $request->validated(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment submission updated successfully',
                'data' => new OrderResource($order),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
