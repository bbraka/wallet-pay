<?php

namespace App\Services\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function getTransactionsWithFilters(array $filters = []): LengthAwarePaginator
    {
        $query = Transaction::with(['user:id,name,email', 'creator:id,name,email', 'order'])
            ->orderBy('id', 'desc');

        $this->applyFilters($query, $filters);

        return $query->paginate(25);
    }

    public function createManualTransaction(array $data, User $admin): Transaction
    {
        $this->validateTransactionData($data);
        
        // Get the user for the transaction
        $user = User::findOrFail($data['user_id']);
        
        // Validate balance for debit transactions
        if ($data['type'] === TransactionType::DEBIT->value) {
            $this->validateUserBalance($user, abs($data['amount']));
        }

        return DB::transaction(function () use ($data, $admin, $user) {
            // Store positive amounts for credits, negative for debits
            $amount = abs($data['amount']);
            if ($data['type'] === TransactionType::DEBIT->value) {
                $amount = -$amount;
            }

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => $data['type'],
                'amount' => $amount,
                'status' => TransactionStatus::ACTIVE,
                'description' => $data['description'] ?? 'Manual transaction by admin',
                'created_by' => $admin->id,
                'order_id' => null, // Manual transactions don't have orders
            ]);

            // Wallet balance will be recalculated by TransactionObserver

            // Log admin action
            \Log::info('Admin created manual transaction', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $data['type'],
            ]);

            event(new TransactionCreated($transaction));

            return $transaction->load(['user', 'creator']);
        });
    }

    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        // Don't allow updating system-generated transactions
        if (!$transaction->created_by) {
            throw ValidationException::withMessages([
                'transaction' => ['System-generated transactions cannot be modified.']
            ]);
        }

        // Only allow updating amount and description
        $allowedFields = ['amount', 'description'];
        $data = array_intersect_key($data, array_flip($allowedFields));

        // Validate new amount if provided
        if (isset($data['amount'])) {
            $newAmount = abs($data['amount']);
            
            // For debit transactions, validate balance
            if ($transaction->type === TransactionType::DEBIT) {
                // Calculate what balance would be if we reverse the old transaction
                $effectiveBalance = $transaction->user->wallet_amount - $transaction->amount; // subtract negative amount (adds it back)
                $this->validateUserBalance($transaction->user, $newAmount, $effectiveBalance);
            }
            
            // Store amount with correct sign based on type
            if ($transaction->type === TransactionType::DEBIT) {
                $data['amount'] = -$newAmount;
            } else {
                $data['amount'] = $newAmount;
            }
        }

        return DB::transaction(function () use ($transaction, $data) {
            $transaction->update($data);

            // Wallet balance will be recalculated by TransactionObserver
            event(new TransactionUpdated($transaction));

            return $transaction->load(['user', 'creator']);
        });
    }

    public function canDeleteTransaction(Transaction $transaction): bool
    {
        // Only manual transactions (created by admin) can be deleted
        // System transactions (from orders) cannot be deleted
        return $transaction->created_by && !$transaction->order_id;
    }

    protected function validateTransactionData(array $data): void
    {
        if (empty($data['user_id'])) {
            throw ValidationException::withMessages([
                'user_id' => ['User is required.']
            ]);
        }

        if (empty($data['type']) || !in_array($data['type'], array_column(TransactionType::cases(), 'value'))) {
            throw ValidationException::withMessages([
                'type' => ['Valid transaction type is required.']
            ]);
        }

        if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be a positive number.']
            ]);
        }
    }

    protected function validateUserBalance(User $user, float $amount, ?float $customBalance = null): void
    {
        $balance = $customBalance ?? $user->wallet_amount;
        
        if ($balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => [sprintf('Insufficient balance. User has $%.2f but transaction requires $%.2f', 
                    $balance, $amount)]
            ]);
        }
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
    }
}