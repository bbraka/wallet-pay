<?php

namespace App\Listeners;

use App\Events\WithdrawalDenied;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessWithdrawalDenial implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private OrderService $orderService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(WithdrawalDenied $event): void
    {
        $this->orderService->denyWithdrawal($event->order, $event->reason);
    }
}
