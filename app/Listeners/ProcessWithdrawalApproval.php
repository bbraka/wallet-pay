<?php

namespace App\Listeners;

use App\Events\WithdrawalApproved;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessWithdrawalApproval implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private OrderService $orderService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(WithdrawalApproved $event): void
    {
        $this->orderService->approveWithdrawal($event->order);
    }
}
