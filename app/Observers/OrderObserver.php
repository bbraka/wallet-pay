<?php

namespace App\Observers;

use App\Models\Order;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderStatusChanged;
use App\Services\WalletTransactionService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private WalletTransactionService $walletService
    ) {}

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if status has changed
        if ($order->isDirty('status')) {
            $originalStatus = $order->getOriginal('status');
            $previousStatus = is_string($originalStatus) || is_int($originalStatus) 
                ? OrderStatus::from($originalStatus)
                : $originalStatus;
            $newStatus = $order->status;

            // Dispatch status changed event
            event(new OrderStatusChanged($order, $previousStatus, $newStatus));

            // Create transactions when order becomes completed
            if ($newStatus === OrderStatus::COMPLETED && $previousStatus !== OrderStatus::COMPLETED) {
                $this->createTransactionsForCompletedOrder($order);
            }
        }
    }

    /**
     * Create the necessary transactions when an order is completed
     */
    private function createTransactionsForCompletedOrder(Order $order): void
    {
        try {
            switch ($order->order_type) {
                case OrderType::INTERNAL_TRANSFER:
                    $this->createInternalTransferTransactions($order);
                    break;
                
                case OrderType::USER_TOP_UP:
                    $this->createTopUpTransaction($order);
                    break;
                
                case OrderType::ADMIN_TOP_UP:
                    // Admin top-ups are handled separately and created as completed
                    // No additional transaction creation needed
                    break;
                
                case OrderType::USER_WITHDRAWAL:
                    $this->createWithdrawalTransaction($order);
                    break;
                
                case OrderType::ADMIN_WITHDRAWAL:
                    $this->createWithdrawalTransaction($order);
                    break;
            }

            Log::info('Transactions created for completed order', [
                'order_id' => $order->id,
                'order_type' => $order->order_type->value,
                'amount' => $order->amount,
                'user_id' => $order->user_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create transactions for completed order', [
                'order_id' => $order->id,
                'order_type' => $order->order_type->value,
                'error' => $e->getMessage(),
            ]);
            
            // Re-throw the exception to prevent the order from being marked as completed
            // if transaction creation fails
            throw $e;
        }
    }

    /**
     * Create transactions for internal transfer
     */
    private function createInternalTransferTransactions(Order $order): void
    {
        if (!$order->receiver_user_id) {
            throw new \Exception('Internal transfer order missing receiver_user_id');
        }

        // Create debit transaction for sender
        $this->walletService->withdraw(
            $order->user,
            $order->amount,
            "Transfer to {$order->receiver->name} - Order #{$order->id}",
            $order
        );

        // Create credit transaction for receiver
        $this->walletService->add(
            $order->receiver,
            $order->amount,
            "Transfer from {$order->user->name} - Order #{$order->id}",
            $order
        );
    }

    /**
     * Create transaction for top-up
     */
    private function createTopUpTransaction(Order $order): void
    {
        $this->walletService->add(
            $order->user,
            $order->amount,
            "Top-up via {$order->topUpProvider->name} - Order #{$order->id}",
            $order
        );
    }

    /**
     * Create transaction for withdrawal
     */
    private function createWithdrawalTransaction(Order $order): void
    {
        $this->walletService->withdraw(
            $order->user,
            $order->amount,
            "Withdrawal - Order #{$order->id}",
            $order
        );
    }
}