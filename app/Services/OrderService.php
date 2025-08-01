<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\MoneyAddedEvent;
use App\Events\MoneyWithdrawnEvent;
use App\Events\OrderStatusChanged;
use App\Events\TransactionCancelledEvent;
use App\Exceptions\Wallet\InsufficientBalanceException;
use App\Exceptions\Wallet\InvalidOrderStatusException;
use App\Exceptions\Wallet\InvalidTopUpProviderException;
use App\Exceptions\Wallet\MissingProviderReferenceException;
use App\Models\Order;
use App\Models\TopUpProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private WalletTransactionService $walletService
    ) {}

    /**
     * Create an internal transfer order between users
     */
    public function createInternalTransferOrder(
        User $sender, 
        User $receiver, 
        float $amount, 
        string $title, 
        ?string $description = null
    ): Order {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (!$this->walletService->hasSufficientBalance($sender, $amount)) {
            throw new InsufficientBalanceException("Insufficient balance for transfer. Required: {$amount}, Available: {$this->walletService->calculateUserBalance($sender)}");
        }

        return DB::transaction(function () use ($sender, $receiver, $amount, $title, $description) {
            $order = Order::create([
                'title' => $title,
                'amount' => $amount,
                'status' => OrderStatus::PENDING_PAYMENT,
                'order_type' => OrderType::INTERNAL_TRANSFER,
                'description' => $description,
                'user_id' => $sender->id,
                'receiver_user_id' => $receiver->id,
            ]);

            // Transactions will be created by OrderObserver when order is completed
            // This avoids double-spending by keeping money locked until transfer is confirmed

            return $order;
        });
    }

    /**
     * Create a user-initiated top-up order
     */
    public function createUserTopUpOrder(
        User $user, 
        float $amount, 
        string $title, 
        TopUpProvider $provider, 
        ?string $providerReference = null, 
        ?string $description = null
    ): Order {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (!$provider->is_active) {
            throw new InvalidTopUpProviderException('Selected provider is not active');
        }

        if (!$provider->validateReference($providerReference)) {
            throw new MissingProviderReferenceException("Provider '{$provider->name}' requires a reference number");
        }

        return Order::create([
            'title' => $title,
            'amount' => $amount,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::USER_TOP_UP,
            'description' => $description,
            'user_id' => $user->id,
            'top_up_provider_id' => $provider->id,
            'provider_reference' => $providerReference,
        ]);
    }

    /**
     * Create an admin-initiated top-up order (completes immediately)
     */
    public function createAdminTopUpOrder(
        User $targetUser, 
        User $admin, 
        float $amount, 
        string $title, 
        TopUpProvider $provider, 
        ?string $providerReference = null, 
        ?string $description = null
    ): Order {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (!$provider->is_active) {
            throw new InvalidTopUpProviderException('Selected provider is not active');
        }

        if (!$provider->validateReference($providerReference)) {
            throw new MissingProviderReferenceException("Provider '{$provider->name}' requires a reference number");
        }

        return DB::transaction(function () use ($targetUser, $admin, $amount, $title, $provider, $providerReference, $description) {
            $order = Order::create([
                'title' => $title,
                'amount' => $amount,
                'status' => OrderStatus::COMPLETED, // Admin top-ups complete immediately
                'order_type' => OrderType::ADMIN_TOP_UP,
                'description' => $description,
                'user_id' => $targetUser->id,
                'top_up_provider_id' => $provider->id,
                'provider_reference' => $providerReference,
            ]);

            // Dispatch status change event for completion date tracking
            event(new OrderStatusChanged($order, OrderStatus::PENDING_PAYMENT, OrderStatus::COMPLETED));

            // Immediately add money to target user's wallet
            event(new MoneyAddedEvent($targetUser, $amount, $order));

            return $order;
        });
    }

    /**
     * Confirm a pending payment order
     */
    public function confirmPayment(Order $order, User $confirmer): void
    {
        if (!$order->canBeConfirmed()) {
            throw new InvalidOrderStatusException('Order cannot be confirmed in its current status');
        }

        DB::transaction(function () use ($order, $confirmer) {
            $previousStatus = $order->status;
            $order->update(['status' => OrderStatus::COMPLETED]);

            // Dispatch status change event for completion date tracking
            event(new OrderStatusChanged($order, $previousStatus, OrderStatus::COMPLETED));

            // Note: Wallet transactions will be created by OrderObserver
            // when it detects the status change to COMPLETED
        });
    }

    /**
     * Reject a pending payment order
     */
    public function rejectPayment(Order $order, User $rejector): void
    {
        if (!$order->canBeRejected()) {
            throw new InvalidOrderStatusException('Order cannot be rejected in its current status');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::CANCELLED]);

            // If this was an internal transfer, we need to refund the sender
            if ($order->order_type === OrderType::INTERNAL_TRANSFER) {
                // Find the withdrawal transaction and cancel it
                $withdrawalTransaction = $order->transactions()->where('user_id', $order->user_id)->first();
                if ($withdrawalTransaction) {
                    event(new TransactionCancelledEvent($withdrawalTransaction));
                }
            }
        });
    }

    /**
     * Refund a completed order
     */
    public function refundOrder(Order $order, User $refunder): void
    {
        if (!$order->canBeRefunded()) {
            throw new InvalidOrderStatusException('Order cannot be refunded in its current status');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::REFUNDED]);

            // Cancel all related transactions
            foreach ($order->transactions as $transaction) {
                event(new TransactionCancelledEvent($transaction));
            }
        });
    }

    // Withdrawal Methods

    /**
     * Process a withdrawal request (creates pending approval order)
     */
    public function processWithdrawalRequest(
        User $user,
        float $amount,
        string $description,
        OrderType $orderType
    ): Order {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Withdrawal amount must be positive');
        }

        // Check if user has sufficient balance for user withdrawals
        if ($orderType === OrderType::USER_WITHDRAWAL && 
            !$this->walletService->hasSufficientBalance($user, $amount)) {
            throw new InsufficientBalanceException(
                "Insufficient balance for withdrawal. Required: {$amount}, Available: {$this->walletService->calculateUserBalance($user)}"
            );
        }

        // Create withdrawal order with pending approval status
        $order = Order::create([
            'title' => $orderType === OrderType::USER_WITHDRAWAL ? 'User Withdrawal Request' : 'Admin Withdrawal',
            'amount' => $amount,
            'status' => OrderStatus::PENDING_APPROVAL,
            'order_type' => $orderType,
            'description' => $description,
            'user_id' => $user->id,
        ]);

        return $order;
    }

    /**
     * Approve a withdrawal order
     */
    public function approveWithdrawal(Order $order): void
    {
        if (!$order->isWithdrawal()) {
            throw new \InvalidArgumentException('Order is not a withdrawal order');
        }

        if (!$order->canBeConfirmed()) {
            throw new InvalidOrderStatusException('Order cannot be approved in its current status');
        }

        // Check balance again at approval time (for user withdrawals)
        if ($order->order_type === OrderType::USER_WITHDRAWAL && 
            !$this->walletService->hasSufficientBalance($order->user, $order->amount)) {
            throw new InsufficientBalanceException(
                "Insufficient balance to complete withdrawal. Required: {$order->amount}, Available: {$this->walletService->calculateUserBalance($order->user)}"
            );
        }

        DB::transaction(function () use ($order) {
            $previousStatus = $order->status;
            $order->update(['status' => OrderStatus::COMPLETED]);

            // Dispatch status change event for completion date tracking
            event(new OrderStatusChanged($order, $previousStatus, OrderStatus::COMPLETED));

            // Note: Withdrawal transaction will be created by OrderObserver
            // when it detects the status change to COMPLETED
        });
    }

    /**
     * Deny a withdrawal order
     */
    public function denyWithdrawal(Order $order, ?string $reason = null): void
    {
        if (!$order->isWithdrawal()) {
            throw new \InvalidArgumentException('Order is not a withdrawal order');
        }

        if (!$order->canBeRejected()) {
            throw new InvalidOrderStatusException('Order cannot be denied in its current status');
        }

        $previousStatus = $order->status;
        $order->update([
            'status' => OrderStatus::CANCELLED,
            'description' => $order->description . ($reason ? " (Denied: {$reason})" : ' (Denied)')
        ]);

        // Dispatch status change event
        event(new OrderStatusChanged($order, $previousStatus, OrderStatus::CANCELLED));
    }

    // Provider Management Methods

    /**
     * Get all active top-up providers
     */
    public function getAllActiveProviders(): Collection
    {
        return TopUpProvider::active()->orderBy('name')->get();
    }

    /**
     * Get provider by code
     */
    public function getProviderByCode(string $code): TopUpProvider
    {
        $provider = TopUpProvider::byCode($code)->first();
        
        if (!$provider) {
            throw new InvalidTopUpProviderException("Provider with code '{$code}' not found");
        }

        return $provider;
    }

    /**
     * Validate provider reference
     */
    public function validateProviderReference(TopUpProvider $provider, ?string $reference): bool
    {
        return $provider->validateReference($reference);
    }

    /**
     * Create a new top-up provider
     */
    public function createProvider(
        string $name, 
        string $code, 
        ?string $description = null, 
        bool $requiresReference = false
    ): TopUpProvider {
        return TopUpProvider::create([
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'is_active' => true,
            'requires_reference' => $requiresReference,
        ]);
    }

    /**
     * Deactivate a provider
     */
    public function deactivateProvider(TopUpProvider $provider): void
    {
        $provider->deactivate();
    }

    /**
     * Get order by ID with relationships
     */
    public function getOrderById(int $id): ?Order
    {
        return Order::with(['user', 'receiver', 'topUpProvider', 'transactions'])
            ->find($id);
    }

    /**
     * Get user's orders
     */
    public function getUserOrders(User $user, ?OrderType $type = null, ?OrderStatus $status = null, int $limit = 50)
    {
        $query = Order::where('user_id', $user->id)
            ->with(['receiver', 'topUpProvider', 'transactions'])
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->byType($type);
        }

        if ($status) {
            $query->byStatus($status);
        }

        return $query->limit($limit)->get();
    }
}