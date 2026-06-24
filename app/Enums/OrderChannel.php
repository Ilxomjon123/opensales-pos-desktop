<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderChannel: string
{
    case BOT = 'bot';
    case MANUAL = 'manual';
    case MARKETPLACE = 'marketplace';
    case MOBILE_APP = 'mobile_app';

    public function label(): string
    {
        return match ($this) {
            self::BOT => 'Bot',
            self::MANUAL => 'Qo\'lda',
            self::MARKETPLACE => 'Birja',
            self::MOBILE_APP => 'Mobil ilova',
        };
    }

    /**
     * Shop savdosi kanallari (bot + qo'lda) — Birja emas.
     */
    public function isShopChannel(): bool
    {
        return $this !== self::MARKETPLACE;
    }
}
