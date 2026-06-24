<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductVisibility: string
{
    case BOT_ONLY = 'bot_only';
    case MARKETPLACE_ONLY = 'marketplace_only';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::BOT_ONLY => 'Faqat botda',
            self::MARKETPLACE_ONLY => 'Faqat birjada',
            self::BOTH => 'Bot va birjada',
        };
    }

    public function visibleInBot(): bool
    {
        return $this === self::BOT_ONLY || $this === self::BOTH;
    }

    public function visibleInMarketplace(): bool
    {
        return $this === self::MARKETPLACE_ONLY || $this === self::BOTH;
    }
}
