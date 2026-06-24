<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastScheduleType: string
{
    case ONCE = 'once';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';

    public function label(): string
    {
        return (string) __('enums.BroadcastScheduleType.'.$this->value);
    }
}
