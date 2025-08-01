<?php

namespace App\Services\Merchant;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Events\WithdrawalRequested;
use App\Models\Order;
use App\Models\TopUpProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdersService
{
    public function getOrdersWithFilters(User $user, array $filters): LengthAwarePaginator
    {
        $query = Order::where('user_id', $user->id)
            ->with(['receiver:id,name,email', 'topUpProvider:id,name,code'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->paginate(15);
    }

    public function getPendingTransfersReceived(User $user): array
    {
        return Order::where('receiver_user_id', $user->id)
            ->where('order_type', OrderType::INTERNAL_TRANSFER)
            ->whereIn('status', [OrderStatus::PENDING_PAYMENT, OrderStatus::PENDING_APPROVAL])
            ->with(['user:id,name,email', 'receiver:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function createOrder(User $user, array $data): Order
    {
        $this->validateOrderData($data);

        $orderType = $this->determineOrderType($data);
        $this->validateAmountLimit($data['amount'], $orderType);

        if ($orderType === OrderType::INTERNAL_TRANSFER) {
            $this->validateReceiver($data['receiver_user_id'], $user->id);
        }

        if ($orderType->isTopUp()) {
            $this->validateTopUpProvider($data['top_up_provider_id'] ?? null);
        }

        return DB::transaction(function () use ($user, $data, $orderType) {
            // Get receiver user if this is a transfer
            $receiver = null;
            if ($orderType === OrderType::INTERNAL_TRANSFER && !empty($data['receiver_user_id'])) {
                $receiver = User::find($data['receiver_user_id']);
            }

            $order = Order::create([
                'title' => $data['title'],
                'amount' => $data['amount'],
                'status' => OrderStatus::PENDING_PAYMENT,
                'order_type' => $orderType,
                'description' => $this->generateOrderDescription(
                    $orderType,
                    $user,
                    $receiver,
                    null,
                    $data['description'] ?? null
                ),
                'user_id' => $user->id,
                'receiver_user_id' => $data['receiver_user_id'] ?? null,
                'top_up_provider_id' => $data['top_up_provider_id'] ?? null,
                'provider_reference' => $data['provider_reference'] ?? null,
            ]);

            // Update the order description with the actual order ID
            $order->update([
                'description' => $this->generateOrderDescription(
                    $orderType,
                    $user,
                    $receiver,
                    $order->id,
                    $data['description'] ?? null
                )
            ]);

            event(new OrderCreated($order));

            return $order->load(['receiver:id,name,email', 'topUpProvider:id,name,code']);
        });
    }

    public function updateOrder(Order $order, array $data): Order
    {
        if (!$order->status->canBeConfirmed()) {
            throw ValidationException::withMessages([
                'order' => ['Order cannot be updated in its current status.']
            ]);
        }

        $this->validateAmountLimit($data['amount'] ?? $order->amount, $order->order_type);

        return DB::transaction(function () use ($order, $data) {
            $order->update(array_filter([
                'title' => $data['title'] ?? $order->title,
                'amount' => $data['amount'] ?? $order->amount,
                'description' => $data['description'] ?? $order->description,
                'provider_reference' => $data['provider_reference'] ?? $order->provider_reference,
            ]));

            event(new OrderUpdated($order));

            return $order->load(['receiver:id,name,email', 'topUpProvider:id,name,code']);
        });
    }

    public function cancelOrder(Order $order): Order
    {
        if (!$order->status->canBeRejected()) {
            throw ValidationException::withMessages([
                'order' => ['Order cannot be cancelled in its current status.']
            ]);
        }

        return DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::CANCELLED]);

            event(new OrderCancelled($order));

            return $order->load(['receiver:id,name,email', 'topUpProvider:id,name,code']);
        });
    }

    public function confirmOrder(Order $order): Order
    {
        if (!$order->status->canBeConfirmed()) {
            throw ValidationException::withMessages([
                'order' => ['Order cannot be confirmed in its current status.']
            ]);
        }

        return DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::COMPLETED]);

            // OrderObserver will handle transaction creation and event dispatching
            
            return $order->load(['receiver:id,name,email', 'topUpProvider:id,name,code', 'user:id,name,email']);
        });
    }

    public function rejectOrder(Order $order): Order
    {
        if (!$order->status->canBeRejected()) {
            throw ValidationException::withMessages([
                'order' => ['Order cannot be rejected in its current status.']
            ]);
        }

        return DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::CANCELLED]);

            event(new OrderCancelled($order));

            return $order->load(['receiver:id,name,email', 'topUpProvider:id,name,code', 'user:id,name,email']);
        });
    }

    public function createWithdrawalRequest(User $user, array $data): Order
    {
        // Validation is handled by CreateWithdrawalRequest
        $this->validateAmountLimit($data['amount'], OrderType::USER_WITHDRAWAL);

        return DB::transaction(function () use ($user, $data) {
            // Generate description for withdrawal
            $description = $this->generateOrderDescription(
                OrderType::USER_WITHDRAWAL,
                $user,
                null,
                null,
                $data['description'] ?? null
            );

            // Dispatch withdrawal requested event - listener will create the order
            event(new WithdrawalRequested(
                $user,
                $data['amount'],
                $description,
                OrderType::USER_WITHDRAWAL
            ));

            // Find the created order by latest withdrawal for this user
            $order = Order::where('user_id', $user->id)
                ->where('order_type', OrderType::USER_WITHDRAWAL)
                ->orderBy('created_at', 'desc')
                ->first();

            // Update description with order ID
            if ($order) {
                $order->update([
                    'description' => $this->generateOrderDescription(
                        OrderType::USER_WITHDRAWAL,
                        $user,
                        null,
                        $order->id,
                        $data['description'] ?? null
                    )
                ]);
            }

            return $order->load(['user']);
        });
    }

    public function getValidationRules(): array
    {
        return [
            'max_top_up_amount' => Order::MAX_TOP_UP_AMOUNT,
            'max_transfer_amount' => Order::MAX_TRANSFER_AMOUNT,
            'max_withdrawal_amount' => Order::MAX_TRANSFER_AMOUNT, // Same limit as transfers
            'required_fields' => [
                'top_up' => ['title', 'amount', 'top_up_provider_id'],
                'transfer' => ['title', 'amount', 'receiver_user_id'],
                'withdrawal' => ['amount'],
            ],
            'allowed_statuses' => [
                OrderStatus::PENDING_PAYMENT->value,
                OrderStatus::PENDING_APPROVAL->value,
                OrderStatus::COMPLETED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ],
        ];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['receiver_user_id'])) {
            $query->where('receiver_user_id', $filters['receiver_user_id']);
        }
    }

    protected function determineOrderType(array $data): OrderType
    {
        if (!empty($data['receiver_user_id'])) {
            return OrderType::INTERNAL_TRANSFER;
        }

        if (!empty($data['top_up_provider_id'])) {
            return OrderType::USER_TOP_UP;
        }

        throw ValidationException::withMessages([
            'order' => ['Either receiver_user_id or top_up_provider_id must be provided.']
        ]);
    }

    protected function validateAmountLimit(float $amount, OrderType $orderType): void
    {
        $maxAmount = match ($orderType) {
            OrderType::INTERNAL_TRANSFER => Order::MAX_TRANSFER_AMOUNT,
            OrderType::USER_TOP_UP, OrderType::ADMIN_TOP_UP => Order::MAX_TOP_UP_AMOUNT,
            OrderType::USER_WITHDRAWAL, OrderType::ADMIN_WITHDRAWAL => Order::MAX_TRANSFER_AMOUNT,
        };

        if ($amount > $maxAmount) {
            throw ValidationException::withMessages([
                'amount' => ["Amount cannot exceed {$maxAmount} for {$orderType->label()}."]
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than 0.']
            ]);
        }
    }

    protected function validateReceiver(?int $receiverUserId, int $senderUserId): void
    {
        if (!$receiverUserId) {
            throw ValidationException::withMessages([
                'receiver_user_id' => ['Receiver user ID is required for transfers.']
            ]);
        }

        if ($receiverUserId === $senderUserId) {
            throw ValidationException::withMessages([
                'receiver_user_id' => ['Cannot transfer to yourself.']
            ]);
        }

        if (!User::find($receiverUserId)) {
            throw ValidationException::withMessages([
                'receiver_user_id' => ['Receiver user not found.']
            ]);
        }
    }

    protected function validateTopUpProvider(?int $topUpProviderId): void
    {
        if (!$topUpProviderId) {
            throw ValidationException::withMessages([
                'top_up_provider_id' => ['Top-up provider ID is required for top-ups.']
            ]);
        }

        $provider = TopUpProvider::find($topUpProviderId);
        if (!$provider) {
            throw ValidationException::withMessages([
                'top_up_provider_id' => ['Top-up provider not found.']
            ]);
        }

        if (!$provider->is_active) {
            throw ValidationException::withMessages([
                'top_up_provider_id' => ['Selected top-up provider is not active.']
            ]);
        }

        // Prevent merchants from using admin adjustment provider
        if ($provider->code === 'admin_adjustment') {
            throw ValidationException::withMessages([
                'top_up_provider_id' => ['Admin adjustment provider is not available for merchant use.']
            ]);
        }
    }

    protected function validateOrderData(array $data): void
    {
        if (empty($data['title'])) {
            throw ValidationException::withMessages([
                'title' => ['Title is required.']
            ]);
        }

        if (empty($data['amount']) || !is_numeric($data['amount'])) {
            throw ValidationException::withMessages([
                'amount' => ['Amount is required and must be numeric.']
            ]);
        }
    }

    protected function validateWithdrawalData(array $data): void
    {
        if (empty($data['amount']) || !is_numeric($data['amount'])) {
            throw ValidationException::withMessages([
                'amount' => ['Amount is required and must be numeric.']
            ]);
        }

        if ($data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than 0.']
            ]);
        }
    }

    /**
     * Generate a descriptive description for an order based on its type and context
     */
    public function generateOrderDescription(OrderType $orderType, User $user, ?User $receiver = null, ?int $orderId = null, ?string $customDescription = null): string
    {
        // If custom description is provided, use it
        if (!empty($customDescription)) {
            return $customDescription;
        }

        return match ($orderType) {
            OrderType::INTERNAL_TRANSFER => "Received funds from {$user->email}" . ($receiver ? " to {$receiver->email}" : ''),
            OrderType::USER_TOP_UP => "Order purchased funds #{$orderId}" . ($orderId ? " - User top-up by {$user->email}" : " - User top-up by {$user->email}"),
            OrderType::ADMIN_TOP_UP => "Admin top-up for {$user->email}" . ($orderId ? " - Order #{$orderId}" : ''),
            OrderType::USER_WITHDRAWAL => "User withdrawal request by {$user->email}" . ($orderId ? " - Order #{$orderId}" : ''),
            OrderType::ADMIN_WITHDRAWAL => "Admin withdrawal for {$user->email}" . ($orderId ? " - Order #{$orderId}" : ''),
        };
    }

    public function createAdminTopUp(User $targetUser, array $data, User $adminUser): Order
    {
        $this->validateOrderData($data);
        $this->validateAmountLimit($data['amount'], OrderType::ADMIN_TOP_UP);
        $this->validateTopUpProvider($data['top_up_provider_id'] ?? null);

        return DB::transaction(function () use ($targetUser, $data, $adminUser) {
            // Create order as completed since admin top-ups are immediate
            $order = Order::create([
                'title' => $data['title'],
                'amount' => $data['amount'],
                'status' => OrderStatus::COMPLETED,
                'order_type' => OrderType::ADMIN_TOP_UP,
                'description' => $this->generateOrderDescription(
                    OrderType::ADMIN_TOP_UP, 
                    $targetUser, 
                    null, 
                    null, 
                    $data['description'] ?? null
                ),
                'user_id' => $targetUser->id,
                'receiver_user_id' => null,
                'top_up_provider_id' => $data['top_up_provider_id'],
                'provider_reference' => $data['provider_reference'] ?? null,
            ]);

            // Update the order description with the actual order ID
            $order->update([
                'description' => $this->generateOrderDescription(
                    OrderType::ADMIN_TOP_UP, 
                    $targetUser, 
                    null, 
                    $order->id, 
                    $data['description'] ?? null
                )
            ]);

            // Create transaction record (observer will update wallet)
            \App\Models\Transaction::create([
                'user_id' => $targetUser->id,
                'type' => \App\Enums\TransactionType::CREDIT,
                'amount' => $data['amount'],
                'status' => \App\Enums\TransactionStatus::ACTIVE,
                'description' => $this->generateOrderDescription(
                    OrderType::ADMIN_TOP_UP, 
                    $targetUser, 
                    null, 
                    $order->id, 
                    $data['description'] ?? null
                ),
                'created_by' => $adminUser->id, // Admin who created the transaction
                'order_id' => $order->id,
            ]);

            // Log admin action
            \Log::info('Admin created top-up order', [
                'admin_id' => $adminUser->id,
                'admin_email' => $adminUser->email,
                'order_id' => $order->id,
                'target_user_id' => $targetUser->id,
                'amount' => $data['amount'],
            ]);

            event(new OrderCreated($order));

            return $order->load(['user', 'topUpProvider']);
        });
    }
}