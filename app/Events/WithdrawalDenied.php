<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalDenied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public User $deniedBy,
        public ?string $reason = null
    ) {
    }
}
