<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastMessageStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return (string) __('enums.BroadcastMessageStatus.'.$this->value);
    }
}
