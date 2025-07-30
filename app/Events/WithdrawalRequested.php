<?php

namespace App\Events;

use App\Models\User;
use App\Enums\OrderType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public float $amount,
        public string $description,
        public OrderType $orderType
    ) {
    }
}
