<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionType: string
{
    case TURNOVER_PERCENTAGE = 'turnover_percentage';
    case FIXED_PER_SHOP = 'fixed_per_shop';
    case FIXED_PER_ORDER = 'fixed_per_order';
    case FIXED_PER_DELIVERYMAN = 'fixed_per_deliveryman';
    case FIXED_MONTHLY = 'fixed_monthly';

    public function label(): string
    {
        return (string) __('enums.CommissionType.'.$this->value);
    }

    public function shortLabel(): string
    {
        return (string) __('enums.CommissionType.short.'.$this->value);
    }

    public function usesFixedAmount(): bool
    {
        return $this === self::FIXED_PER_SHOP
            || $this === self::FIXED_PER_ORDER
            || $this === self::FIXED_PER_DELIVERYMAN
            || $this === self::FIXED_MONTHLY;
    }
}
