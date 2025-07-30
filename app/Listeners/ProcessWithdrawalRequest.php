<?php

namespace App\Listeners;

use App\Events\WithdrawalRequested;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessWithdrawalRequest
{

    public function __construct(
        private OrderService $orderService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(WithdrawalRequested $event): void
    {
        $this->orderService->processWithdrawalRequest(
            $event->user,
            $event->amount,
            $event->description,
            $event->orderType
        );
    }
}
