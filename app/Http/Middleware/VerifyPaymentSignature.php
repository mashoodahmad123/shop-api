<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.payment.webhook_secret');

        if ($secret === '') {
            Log::critical('PAYMENT_WEBHOOK_SECRET is not configured.');

            return $this->reject('Webhook is not configured.', 500);
        }

        $header = (string) $request->header('X-Signature', '');
        $signature = str_starts_with($header, 'sha256=') ? substr($header, 7) : $header;

        if ($signature === '') {
            return $this->reject('Missing signature.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, strtolower($signature))) {
            Log::warning('Payment webhook rejected: invalid signature.', ['ip' => $request->ip()]);

            return $this->reject('Invalid signature.');
        }

        return $next($request);
    }

    protected function reject(string $message, int $status = 401): Response
    {
        return response()->json([
            'status' => false,
            'message' => $message,
        ], $status);
    }
}
