<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }

    public function confirm(User $user, Order $order): bool
    {
        // Only the receiver of a transfer can confirm it
        return $user->id === $order->receiver_user_id;
    }

    public function reject(User $user, Order $order): bool
    {
        // Only the receiver of a transfer can reject it
        return $user->id === $order->receiver_user_id;
    }
}