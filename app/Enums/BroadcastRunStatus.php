<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return (string) __('enums.BroadcastRunStatus.'.$this->value);
    }
}
