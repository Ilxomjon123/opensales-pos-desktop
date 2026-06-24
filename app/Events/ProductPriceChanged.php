<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProductPriceChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly float $oldPrice,
        public readonly ?float $oldPackPrice,
        public readonly float $newPrice,
        public readonly ?float $newPackPrice,
    ) {}
}
