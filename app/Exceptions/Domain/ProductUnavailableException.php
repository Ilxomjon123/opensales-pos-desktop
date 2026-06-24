<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Models\Product;

final class ProductUnavailableException extends DomainException
{
    public static function forProduct(?Product $product, string $name): self
    {
        $label = $product?->name ?? $name;

        return new self("'{$label}' mahsuloti mavjud emas yoki sotuvdan olingan");
    }
}
