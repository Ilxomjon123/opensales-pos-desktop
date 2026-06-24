<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case STOCK_IN = 'stock_in';
    case STOCK_OUT = 'stock_out';
    case STOCK_ADJUST = 'stock_adjust';
    case SHOP_RETURN = 'shop_return';
    case SUPPLIER_RETURN = 'supplier_return';

    public function label(): string
    {
        return (string) __('enums.TransactionType.'.$this->value);
    }

    /**
     * Stok delta belgisi: +1 (prixod), -1 (chiqim), 0 (tuzatish — qiymat to'g'ridan).
     * SHOP_RETURN — stok ortadi, SUPPLIER_RETURN — stok kamayadi.
     */
    public function stockSign(): int
    {
        return match ($this) {
            self::STOCK_IN, self::SHOP_RETURN => 1,
            self::STOCK_OUT, self::SUPPLIER_RETURN => -1,
            self::STOCK_ADJUST => 0,
        };
    }

    public function isReturn(): bool
    {
        return in_array($this, [self::SHOP_RETURN, self::SUPPLIER_RETURN], true);
    }
}
