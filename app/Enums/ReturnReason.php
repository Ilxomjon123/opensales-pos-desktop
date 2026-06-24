<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnReason: string
{
    case DEFECTIVE = 'defective';
    case EXPIRED = 'expired';
    case WRONG_ITEM = 'wrong_item';
    case UNSOLD = 'unsold';
    case DAMAGED = 'damaged';
    case OTHER = 'other';

    public function label(): string
    {
        return (string) __('enums.ReturnReason.'.$this->value);
    }
}
