<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case DEBT = 'debt';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'To\'lanmagan',
            self::PARTIAL => 'Qisman to\'langan',
            self::PAID => 'To\'langan',
            self::DEBT => 'Qarzga',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PAID => 'emerald',
            self::PARTIAL => 'amber',
            self::UNPAID => 'slate',
            self::DEBT => 'rose',
        };
    }

    public static function resolve(int $total, int $paid): self
    {
        if ($total <= 0) {
            return self::PAID;
        }

        if ($paid <= 0) {
            return self::DEBT;
        }

        if ($paid >= $total) {
            return self::PAID;
        }

        return self::PARTIAL;
    }
}
