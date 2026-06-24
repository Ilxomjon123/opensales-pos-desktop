<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    public function label(): string
    {
        return (string) __('enums.PaymentType.'.$this->value);
    }

    public function sign(): int
    {
        return match ($this) {
            self::CREDIT => 1,
            self::DEBIT => -1,
        };
    }
}
