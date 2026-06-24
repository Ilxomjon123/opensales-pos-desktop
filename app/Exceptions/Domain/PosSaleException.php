<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class PosSaleException extends DomainException
{
    public static function emptyCart(): self
    {
        return new self('Savat bo\'sh — kamida bitta mahsulot tanlang.');
    }

    public static function debtRequiresCustomer(): self
    {
        return new self('Qarzga sotish uchun mijozni tanlash shart (yo\'lakay xaridorga qarzga sotib bo\'lmaydi).');
    }

    public static function overpayment(): self
    {
        return new self('To\'langan summa zakas summasidan ortiq bo\'lishi mumkin emas.');
    }
}
