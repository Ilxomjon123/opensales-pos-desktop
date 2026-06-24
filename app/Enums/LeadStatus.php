<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case CONVERTED = 'converted';
    case DROPPED = 'dropped';

    public function label(): string
    {
        return (string) __('enums.LeadStatus.'.$this->value);
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::NEW => 'default',
            self::CONTACTED => 'secondary',
            self::CONVERTED => 'default',
            self::DROPPED => 'outline',
        };
    }
}
