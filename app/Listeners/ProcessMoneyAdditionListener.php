<?php

namespace App\Listeners;

use App\Events\MoneyAddedEvent;
use App\Services\WalletTransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessMoneyAdditionListener
{

    public function __construct(
        private WalletTransactionService $walletService
    ) {}

    public function handle(MoneyAddedEvent $event): void
    {
        try {
            $this->walletService->add(
                $event->user,
                $event->amount,
                "Credit for order: {$event->order->title}",
                $event->order
            );

            Log::info('Money addition processed', [
                'user_id' => $event->user->id,
                'amount' => $event->amount,
                'order_id' => $event->order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process money addition', [
                'user_id' => $event->user->id,
                'amount' => $event->amount,
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}