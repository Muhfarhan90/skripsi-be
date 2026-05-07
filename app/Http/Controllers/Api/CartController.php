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
        $cart = $this->service->addCourseToCart(
            (int) $request->user()->id,
            (int) $request->validated()['course_id'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Course added to cart successfully',
            'data' => new OrderResource($cart),
        ], 201);
    }

    public function removeItem(Request $request, string $courseId)
    {
        $cart = $this->service->removeCourseFromCart(
            (int) $request->user()->id,
            (int) $courseId,
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
