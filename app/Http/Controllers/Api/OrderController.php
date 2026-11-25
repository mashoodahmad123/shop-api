<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ListOrdersRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Traits\ApiResponse;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(protected OrderService $orderService)
    {
    }

    public function index(ListOrdersRequest $request)
    {
        $orders = $this->orderService->listForCustomer($request->user(), $request->validated());

        return $this->success(
            OrderResource::collection($orders)->response()->getData(true)
        );
    }

    public function store(StoreOrderRequest $request)
    {
        $order = $this->orderService->place($request->user(), $request->validated('items'));

        return $this->success(new OrderResource($order), 'Order placed successfully', 201);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return $this->success(new OrderResource($order->load('items')));
    }

    public function cancel(Order $order)
    {
        $this->authorize('cancel', $order);

        $order = $this->orderService->cancelByCustomer($order);

        return $this->success(new OrderResource($order), 'Order cancelled successfully');
    }
}
