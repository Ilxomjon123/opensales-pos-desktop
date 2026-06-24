<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleChannel: string
{
    case TELEGRAM = 'telegram';
    case POS = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::TELEGRAM => 'Telegram',
            self::POS => 'POS kassa',
        };
    }
}
