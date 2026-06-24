<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';

    public function label(): string
    {
        return (string) __('enums.PaymentMethod.'.$this->value);
    }
}
