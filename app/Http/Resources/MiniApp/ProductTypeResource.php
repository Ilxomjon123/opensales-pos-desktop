<?php

declare(strict_types=1);

namespace App\Http\Resources\MiniApp;

use App\Models\Product;
use App\Models\ProductType;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Mini app uchun mahsulot tipi — mijoz vakiliga ko'rsatiladigan ma'lumotlar.
 * Stock va u bilan bog'liq maydonlar BERILMAYDI.
 *
 * @mixin ProductType
 */
final class ProductTypeResource extends JsonResource
{
    public function __construct(ProductType $resource, private readonly ?Product $product = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $images = $this->relationLoaded('images')
            ? $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => Storage::url($img->path),
                'sort_order' => $img->sort_order,
            ])->values()
            : collect();

        $packSize = max(1.0, (float) $this->pack_size);
        $product = $this->product ?? $this->resource->product;

        $effectivePrice = $product !== null
            ? app(PromotionService::class)->effectivePriceForType($product, $this->resource)
            : (float) $this->price;

        $hasDiscount = abs($effectivePrice - (float) $this->price) > 0.001;

        $basePackPrice = $this->pack_price !== null
            ? (float) $this->pack_price
            : round((float) $this->price * $packSize, 2);
        $effectivePackPrice = $hasDiscount && (float) $this->price > 0
            ? round($basePackPrice * $effectivePrice / (float) $this->price, 2)
            : $basePackPrice;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'price' => $effectivePrice,
            'original_price' => $hasDiscount ? (float) $this->price : null,
            'pack_size' => $packSize,
            'pack_price' => $effectivePackPrice,
            'original_pack_price' => $hasDiscount ? $basePackPrice : null,
            'bulk_only' => (bool) $this->bulk_only,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'images' => $images,
            'image_url' => $images->first()['url'] ?? null,
        ];
    }
}
