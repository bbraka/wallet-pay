<?php

namespace App\Enums;

enum TransactionType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    public function label(): string
    {
        return match($this) {
            self::CREDIT => 'Credit',
            self::DEBIT => 'Debit',
        };
    }

    public function getAmountMultiplier(): int
    {
        return match($this) {
            self::CREDIT => 1,
            self::DEBIT => -1,
        };
    }

    public static function fromAmount(float $amount): self
    {
        return $amount >= 0 ? self::CREDIT : self::DEBIT;
    }
}