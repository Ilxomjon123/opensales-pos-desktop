<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\Currency;
use App\Support\Money;

final class BelowMinOrderAmountException extends DomainException
{
    public static function make(int $cartTotal, int $minimum, Currency $currency = Currency::UZS): self
    {
        $minFormatted = Money::formatWithSymbol($minimum, $currency);
        $totalFormatted = Money::formatWithSymbol($cartTotal, $currency);

        return new self(
            "Buyurtma berish uchun savat summasi kamida {$minFormatted} bo'lishi kerak. Hozir: {$totalFormatted}."
        );
    }
}
