<?php

namespace App\Enums;

enum OrderType: string
{
    case INTERNAL_TRANSFER = 'internal_transfer';
    case USER_TOP_UP = 'user_top_up';
    case ADMIN_TOP_UP = 'admin_top_up';

    public function label(): string
    {
        return match($this) {
            self::INTERNAL_TRANSFER => 'Internal Transfer',
            self::USER_TOP_UP => 'User Top-up',
            self::ADMIN_TOP_UP => 'Admin Top-up',
        };
    }

    public function isTopUp(): bool
    {
        return match($this) {
            self::USER_TOP_UP, self::ADMIN_TOP_UP => true,
            self::INTERNAL_TRANSFER => false,
        };
    }

    public function requiresReceiver(): bool
    {
        return match($this) {
            self::INTERNAL_TRANSFER => true,
            self::USER_TOP_UP, self::ADMIN_TOP_UP => false,
        };
    }
}