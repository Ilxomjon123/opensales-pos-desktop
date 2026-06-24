<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionScope: string
{
    case ALL = 'all';
    case CATEGORY = 'category';
    case PRODUCT = 'product';

    public function label(): string
    {
        return (string) __('enums.PromotionScope.'.$this->value);
    }
}
