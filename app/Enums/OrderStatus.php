<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PENDING_PAYMENT => 'Pending Payment',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }

    public function isActive(): bool
    {
        return match($this) {
            self::COMPLETED => true,
            self::PENDING_PAYMENT, self::CANCELLED, self::REFUNDED => false,
        };
    }

    public function canBeConfirmed(): bool
    {
        return $this === self::PENDING_PAYMENT;
    }

    public function canBeRejected(): bool
    {
        return $this === self::PENDING_PAYMENT;
    }

    public function canBeRefunded(): bool
    {
        return $this === self::COMPLETED;
    }
}