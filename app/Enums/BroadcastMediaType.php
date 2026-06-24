<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastMediaType: string
{
    case PHOTO = 'photo';
    case DOCUMENT = 'document';
    case VIDEO = 'video';

    public function label(): string
    {
        return (string) __('enums.BroadcastMediaType.'.$this->value);
    }
}
