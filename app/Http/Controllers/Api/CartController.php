<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\CheckoutCartRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected OrderService $service;

    public function __construct(OrderService $orderService)
    {
        $this->service = $orderService;
    }

    public function show(Request $request)
    {
        $cart = $this->service->getCartForStudent((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Cart retrieved successfully',
            'data' => $cart ? new OrderResource($cart) : null,
        ]);
    }

    public function addItem(AddCartItemRequest $request)
    {
        $validated = $request->validated();
        $offeringId = isset($validated['course_offering_id'])
            ? (int) $validated['course_offering_id']
            : null;
        $courseId = isset($validated['course_id']) ? (int) $validated['course_id'] : null;

        if ($offeringId === null && $courseId === null) {
            return response()->json([
                'success' => false,
                'message' => 'course_offering_id or course_id is required',
            ], 422);
        }

        $cart = $this->service->addCourseToCart(
            (int) $request->user()->id,
            $offeringId,
            $courseId,
        );

        return response()->json([
            'success' => true,
            'message' => 'Course added to cart successfully',
            'data' => new OrderResource($cart),
        ], 201);
    }

    public function removeItem(Request $request, string $itemId)
    {
        $cart = $this->service->removeCourseFromCart(
            (int) $request->user()->id,
            (int) $itemId,
        );

        return response()->json([
            'success' => true,
            'message' => 'Course removed from cart successfully',
            'data' => $cart ? new OrderResource($cart) : null,
        ]);
    }

    public function checkout(CheckoutCartRequest $request)
    {
        $order = $this->service->checkoutCart(
            (int) $request->user()->id,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Checkout created successfully. Please complete manual payment.',
            'data' => new OrderResource($order),
        ]);
    }
}
