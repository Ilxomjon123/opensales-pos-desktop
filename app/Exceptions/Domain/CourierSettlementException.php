<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class CourierSettlementException extends DomainException
{
    public static function amountExceedsBalance(int $amount, int $balance): self
    {
        return new self(
            "Topshirish summasi ({$amount}) yetkazib beruvchi qo'lidagi qoldiqdan ko'p ({$balance})"
        );
    }

    public static function notADeliveryman(): self
    {
        return new self('Foydalanuvchi yetkazib beruvchi emas');
    }

    public static function invalidAmount(): self
    {
        return new self("Topshirish summasi musbat son bo'lishi kerak");
    }
}
