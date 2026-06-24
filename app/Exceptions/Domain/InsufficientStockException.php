<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Models\Product;

final class InsufficientStockException extends DomainException
{
    public static function forProduct(Product $product, int|float $requested): self
    {
        return new self(
            "'{$product->name}': so'raldi {$requested}, mavjud {$product->stock}"
        );
    }
}
