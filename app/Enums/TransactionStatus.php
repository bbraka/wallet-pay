<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }
}