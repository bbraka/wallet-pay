<?php

namespace App\Services\Merchant;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
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
            $order = Order::create([
                'title' => $data['title'],
                'amount' => $data['amount'],
                'status' => OrderStatus::PENDING_PAYMENT,
                'order_type' => $orderType,
                'description' => $data['description'] ?? null,
                'user_id' => $user->id,
                'receiver_user_id' => $data['receiver_user_id'] ?? null,
                'top_up_provider_id' => $data['top_up_provider_id'] ?? null,
                'provider_reference' => $data['provider_reference'] ?? null,
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

    public function getValidationRules(): array
    {
        return [
            'max_top_up_amount' => Order::MAX_TOP_UP_AMOUNT,
            'max_transfer_amount' => Order::MAX_TRANSFER_AMOUNT,
            'required_fields' => [
                'top_up' => ['title', 'amount', 'top_up_provider_id'],
                'transfer' => ['title', 'amount', 'receiver_user_id'],
            ],
            'allowed_statuses' => [
                OrderStatus::PENDING_PAYMENT->value,
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
            $query->where('status', $filters['status']);
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
}