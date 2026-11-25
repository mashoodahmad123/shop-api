<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AdminListOrdersRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
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

    public function index(AdminListOrdersRequest $request)
    {
        $orders = $this->orderService->listForAdmin($request->validated());

        return $this->success(
            OrderResource::collection($orders)->response()->getData(true)
        );
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return $this->success(new OrderResource($order->load(['items', 'user'])));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $order = $this->orderService->updateStatus($order, OrderStatus::from($request->validated('status')));

        return $this->success(new OrderResource($order), 'Order status updated successfully');
    }
}
