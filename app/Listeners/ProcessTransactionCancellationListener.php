<?php

namespace App\Listeners;

use App\Events\TransactionCancelledEvent;
use App\Services\WalletTransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessTransactionCancellationListener
{

    public function __construct(
        private WalletTransactionService $walletService
    ) {}

    public function handle(TransactionCancelledEvent $event): void
    {
        try {
            $this->walletService->cancel($event->transaction);

            Log::info('Transaction cancellation processed', [
                'transaction_id' => $event->transaction->id,
                'user_id' => $event->transaction->user_id,
                'amount' => $event->transaction->amount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process transaction cancellation', [
                'transaction_id' => $event->transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}