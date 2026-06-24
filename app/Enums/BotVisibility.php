<?php

declare(strict_types=1);

namespace App\Enums;

enum BotVisibility: string
{
    case PRIVATE = 'private';
    case PUBLIC = 'public';

    public function label(): string
    {
        return (string) __('enums.BotVisibility.'.$this->value);
    }

    public function isPublic(): bool
    {
        return $this === self::PUBLIC;
    }
}
