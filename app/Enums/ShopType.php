<?php

declare(strict_types=1);

namespace App\Enums;

enum ShopType: string
{
    case TELEGRAM = 'telegram';
    case INDIVIDUAL = 'individual';
    case WALK_IN = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::TELEGRAM => 'Telegram do\'koni',
            self::INDIVIDUAL => 'Mijoz',
            self::WALK_IN => 'Yo\'lakay xaridor',
        };
    }

    public function isPos(): bool
    {
        return $this === self::INDIVIDUAL || $this === self::WALK_IN;
    }
}
