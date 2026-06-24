<?php

declare(strict_types=1);

namespace App\Http\Resources\MiniApp;

use App\Models\Product;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Mini app uchun mahsulot — mijoz vakiliga ko'rsatiladigan ma'lumotlar.
 * Diqqat: ostatka (stock) va u bilan bog'liq maydonlar BERILMAYDI.
 * Qoldiqdan qat'i nazar (manfiy bo'lsa ham) faol mahsulot mini app'da
 * ko'rinadi va buyurtma berilishi mumkin.
 *
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->images->map(fn ($img) => [
            'id' => $img->id,
            'url' => Storage::url($img->path),
            'sort_order' => $img->sort_order,
        ])->values();

        $favoriteIds = $request->attributes->get('favorite_product_ids');

        $promoService = app(PromotionService::class);
        $discountPercent = $promoService->discountPercentFor($this->resource);
        $effectivePrice = $promoService->effectivePriceFor($this->resource);
        $hasDiscount = $discountPercent > 0;
        $packSize = max(1.0, (float) $this->pack_size);

        $hasTypes = (bool) $this->has_types;
        $types = $this->relationLoaded('types')
            ? $this->types->where('is_active', true)
            : collect();

        $startingPrice = $hasTypes && $types->isNotEmpty()
            ? (float) $types->map(fn ($t) => $promoService->effectivePriceForType($this->resource, $t))->min()
            : $effectivePrice;

        $basePackPrice = $this->pack_price !== null
            ? (float) $this->pack_price
            : round((float) $this->price * $packSize, 2);
        $effectivePackPrice = $hasDiscount
            ? round($basePackPrice * (100 - $discountPercent) / 100, 2)
            : $basePackPrice;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $effectivePrice,
            'original_price' => $hasDiscount ? (float) $this->price : null,
            'discount_percent' => $discountPercent,
            'pack_size' => $packSize,
            'pack_price' => $effectivePackPrice,
            'original_pack_price' => $hasDiscount ? $basePackPrice : null,
            'bulk_only' => (bool) $this->bulk_only,
            'has_types' => $hasTypes,
            'starting_price' => $startingPrice,
            'unit' => $this->unit->value,
            'unit_label' => $this->unit->label(),
            'images' => $images,
            'image_url' => $images->first()['url'] ?? null,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'types' => $hasTypes
                ? $types->values()->map(fn ($t) => ProductTypeResource::make($t, $this->resource)->toArray($request))->all()
                : [],
            'is_favorite' => is_array($favoriteIds) ? in_array($this->id, $favoriteIds, true) : null,
        ];
    }
}
