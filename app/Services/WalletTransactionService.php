<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Wallet\InsufficientBalanceException;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class WalletTransactionService
{
    /**
     * Add money to user's wallet (credit transaction with positive amount)
     */
    public function add(User $user, float $amount, string $description, ?Order $order = null): Transaction
    {
        if ($amount <= 0) {
            throw new Exception('Amount must be positive for credit transactions');
        }

        return DB::transaction(function () use ($user, $amount, $description, $order) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::CREDIT,
                'amount' => $amount, // Positive amount
                'status' => TransactionStatus::ACTIVE,
                'description' => $description,
                'created_by' => auth()->id() ?? $user->id,
                'order_id' => $order?->id,
            ]);

            $this->recalculateUserBalance($user);

            return $transaction;
        });
    }

    /**
     * Withdraw money from user's wallet (debit transaction with negative amount)
     */
    public function withdraw(User $user, float $amount, string $description, ?Order $order = null): Transaction
    {
        if ($amount <= 0) {
            throw new Exception('Amount must be positive (will be stored as negative for debit)');
        }

        // Check sufficient balance before withdrawal
        if (!$this->hasSufficientBalance($user, $amount)) {
            throw new InsufficientBalanceException("Insufficient balance. Required: {$amount}, Available: {$this->calculateUserBalance($user)}");
        }

        return DB::transaction(function () use ($user, $amount, $description, $order) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::DEBIT,
                'amount' => -$amount, // Negative amount for withdrawal
                'status' => TransactionStatus::ACTIVE,
                'description' => $description,
                'created_by' => auth()->id() ?? $user->id,
                'order_id' => $order?->id,
            ]);

            $this->recalculateUserBalance($user);

            return $transaction;
        });
    }

    /**
     * Cancel a transaction by changing its status to cancelled
     */
    public function cancel(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->update(['status' => TransactionStatus::CANCELLED]);
            $this->recalculateUserBalance($transaction->user);
        });
    }

    /**
     * Calculate current user balance based on active transactions
     */
    public function calculateUserBalance(User $user): float
    {
        return (float) Transaction::where('user_id', $user->id)
            ->where('status', TransactionStatus::ACTIVE)
            ->sum('amount');
    }

    /**
     * Check if user has sufficient balance for a withdrawal
     */
    public function hasSufficientBalance(User $user, float $amount): bool
    {
        $currentBalance = $this->calculateUserBalance($user);
        return $currentBalance >= $amount;
    }

    /**
     * Recalculate and update user's wallet_amount based on active transactions
     */
    private function recalculateUserBalance(User $user): void
    {
        $balance = $this->calculateUserBalance($user);
        $user->update(['wallet_amount' => $balance]);
    }

    /**
     * Get user's transaction history
     */
    public function getTransactionHistory(User $user, ?TransactionType $type = null, int $limit = 50)
    {
        $query = Transaction::where('user_id', $user->id)
            ->with(['order', 'createdBy'])
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get active transactions for user
     */
    public function getActiveTransactions(User $user)
    {
        return Transaction::where('user_id', $user->id)
            ->active()
            ->with(['order', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}