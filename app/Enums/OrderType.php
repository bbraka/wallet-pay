<?php

namespace App\Enums;

enum OrderType: string
{
    case INTERNAL_TRANSFER = 'internal_transfer';
    case USER_TOP_UP = 'user_top_up';
    case ADMIN_TOP_UP = 'admin_top_up';
    case USER_WITHDRAWAL = 'user_withdrawal';
    case ADMIN_WITHDRAWAL = 'admin_withdrawal';

    public function label(): string
    {
        return match($this) {
            self::INTERNAL_TRANSFER => 'Internal Transfer',
            self::USER_TOP_UP => 'User Top-up',
            self::ADMIN_TOP_UP => 'Admin Top-up',
            self::USER_WITHDRAWAL => 'User Withdrawal',
            self::ADMIN_WITHDRAWAL => 'Admin Withdrawal',
        };
    }

    public function isTopUp(): bool
    {
        return match($this) {
            self::USER_TOP_UP, self::ADMIN_TOP_UP => true,
            self::INTERNAL_TRANSFER, self::USER_WITHDRAWAL, self::ADMIN_WITHDRAWAL => false,
        };
    }

    public function isWithdrawal(): bool
    {
        return match($this) {
            self::USER_WITHDRAWAL, self::ADMIN_WITHDRAWAL => true,
            self::INTERNAL_TRANSFER, self::USER_TOP_UP, self::ADMIN_TOP_UP => false,
        };
    }

    public function requiresReceiver(): bool
    {
        return match($this) {
            self::INTERNAL_TRANSFER => true,
            self::USER_TOP_UP, self::ADMIN_TOP_UP, self::USER_WITHDRAWAL, self::ADMIN_WITHDRAWAL => false,
        };
    }

    public function requiresApproval(): bool
    {
        return match($this) {
            self::USER_WITHDRAWAL, self::ADMIN_WITHDRAWAL => true,
            self::INTERNAL_TRANSFER, self::USER_TOP_UP, self::ADMIN_TOP_UP => false,
        };
    }
}