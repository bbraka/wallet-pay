<?php

namespace App\Listeners;

use App\Events\MoneyWithdrawnEvent;
use App\Services\WalletTransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessMoneyWithdrawalListener
{

    public function __construct(
        private WalletTransactionService $walletService
    ) {}

    public function handle(MoneyWithdrawnEvent $event): void
    {
        try {
            $this->walletService->withdraw(
                $event->user,
                $event->amount,
                "Withdrawal for order: {$event->order->title}",
                $event->order
            );

            Log::info('Money withdrawal processed', [
                'user_id' => $event->user->id,
                'amount' => $event->amount,
                'order_id' => $event->order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process money withdrawal', [
                'user_id' => $event->user->id,
                'amount' => $event->amount,
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}