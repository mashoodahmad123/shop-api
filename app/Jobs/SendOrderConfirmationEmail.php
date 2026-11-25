<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        $order = $this->order->loadMissing(['user', 'items']);

        Mail::to($order->user->email)->send(new OrderConfirmationMail($order));

        Log::info('Order confirmation email sent.', [
            'order_id' => $order->id,
            'email' => $order->user->email,
            'attempt' => $this->attempts(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Order confirmation email failed after all retries.', [
            'order_id' => $this->order->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
