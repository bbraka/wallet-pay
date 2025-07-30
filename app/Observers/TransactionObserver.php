<?php

namespace App\Observers;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\WalletTransactionService;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    public function __construct(
        private WalletTransactionService $walletService
    ) {}

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $this->recalculateUserBalance($transaction);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        // Recalculate if status or amount changed
        if ($transaction->isDirty('status') || $transaction->isDirty('amount')) {
            $this->recalculateUserBalance($transaction);
        }
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        $this->recalculateUserBalance($transaction);
    }

    /**
     * Recalculate user's wallet balance based on active transactions
     */
    private function recalculateUserBalance(Transaction $transaction): void
    {
        try {
            $balance = $this->walletService->calculateUserBalance($transaction->user);

            $transaction->user->update(['wallet_amount' => $balance]);

            Log::debug('User wallet balance recalculated', [
                'user_id' => $transaction->user_id,
                'new_balance' => $balance,
                'transaction_id' => $transaction->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to recalculate user wallet balance', [
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}