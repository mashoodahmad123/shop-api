<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class CancelExpiredOrders extends Command
{
    protected $signature = 'orders:cancel-expired {--minutes=30 : Cancel pending orders older than this many minutes}';

    protected $description = 'Cancel pending orders older than the given age and put their stock back';

    public function handle(OrderService $orderService): int
    {
        $minutes = (int) $this->option('minutes');

        if ($minutes < 1) {
            $this->error('The --minutes option must be at least 1.');

            return self::INVALID;
        }

        $count = $orderService->cancelExpiredPending($minutes);

        $this->info("Cancelled {$count} pending order(s) older than {$minutes} minutes.");

        return self::SUCCESS;
    }
}
