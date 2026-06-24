<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnDisposition: string
{
    case RESTOCK = 'restock';
    case SPOILAGE = 'spoilage';

    public function label(): string
    {
        return (string) __('enums.ReturnDisposition.'.$this->value);
    }

    public function affectsStock(): bool
    {
        return $this === self::RESTOCK;
    }
}
