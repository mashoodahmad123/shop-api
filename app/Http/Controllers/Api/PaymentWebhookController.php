<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentWebhookRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use App\Traits\ApiResponse;

class PaymentWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(protected PaymentService $paymentService)
    {
    }

    public function __invoke(PaymentWebhookRequest $request)
    {
        [$payment, $duplicate] = $this->paymentService->handleWebhook(
            $request->validated(),
            $request->all()
        );

        return $this->success(
            new PaymentResource($payment->load('order')),
            $duplicate ? 'Duplicate webhook ignored' : 'Webhook processed'
        );
    }
}
