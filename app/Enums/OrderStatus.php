<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PENDING_APPROVAL = 'pending_approval';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PENDING_PAYMENT => 'Pending Payment',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }

    public function isActive(): bool
    {
        return match($this) {
            self::COMPLETED => true,
            self::PENDING_PAYMENT, self::PENDING_APPROVAL, self::CANCELLED, self::REFUNDED => false,
        };
    }

    public function isPending(): bool
    {
        return match($this) {
            self::PENDING_PAYMENT, self::PENDING_APPROVAL => true,
            self::COMPLETED, self::CANCELLED, self::REFUNDED => false,
        };
    }

    public function canBeConfirmed(): bool
    {
        return match($this) {
            self::PENDING_PAYMENT, self::PENDING_APPROVAL => true,
            self::COMPLETED, self::CANCELLED, self::REFUNDED => false,
        };
    }

    public function canBeRejected(): bool
    {
        return match($this) {
            self::PENDING_PAYMENT, self::PENDING_APPROVAL => true,
            self::COMPLETED, self::CANCELLED, self::REFUNDED => false,
        };
    }

    public function canBeRefunded(): bool
    {
        return $this === self::COMPLETED;
    }
}