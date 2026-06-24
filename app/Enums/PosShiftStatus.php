<?php

declare(strict_types=1);

namespace App\Enums;

enum PosShiftStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Ochiq',
            self::CLOSED => 'Yopilgan',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }
}
