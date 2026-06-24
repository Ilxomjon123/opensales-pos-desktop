<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->images->map(fn ($img) => [
            'id' => $img->id,
            'url' => Storage::url($img->path),
            'thumb_url' => $img->thumb_path !== null ? Storage::url($img->thumb_path) : Storage::url($img->path),
            'sort_order' => $img->sort_order,
        ])->values();

        $favoriteIds = $request->attributes->get('favorite_product_ids');

        $promoService = app(PromotionService::class);
        $discountPercent = $promoService->discountPercentFor($this->resource);
        $effectivePrice = $promoService->effectivePriceFor($this->resource);
        $hasDiscount = $discountPercent > 0;
        $packSize = max(1.0, (float) $this->pack_size);

        $basePackPrice = $this->pack_price !== null
            ? (float) $this->pack_price
            : round((float) $this->price * $packSize, 2);
        $effectivePackPrice = $hasDiscount
            ? round($basePackPrice * (100 - $discountPercent) / 100, 2)
            : $basePackPrice;

        $hasTypes = (bool) $this->has_types;
        $typesCollection = $this->relationLoaded('types') ? $this->types : null;
        $totalStock = $hasTypes && $typesCollection !== null
            ? (float) $typesCollection->sum('stock')
            : (float) $this->stock;
        $startingPrice = $hasTypes && $typesCollection !== null
            ? (float) ($typesCollection->where('is_active', true)->min('price') ?? 0)
            : $effectivePrice;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $effectivePrice,
            'original_price' => $hasDiscount ? (float) $this->price : null,
            'discount_percent' => $discountPercent,
            'pack_size' => (float) $this->pack_size,
            'pack_price' => $effectivePackPrice,
            'original_pack_price' => $hasDiscount ? $basePackPrice : null,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'pack_cost_price' => $this->pack_cost_price !== null ? (float) $this->pack_cost_price : null,
            'bulk_only' => (bool) $this->bulk_only,
            'has_types' => $hasTypes,
            'types_count' => $typesCollection?->count() ?? 0,
            'total_stock' => $totalStock,
            'starting_price' => $startingPrice,
            'stock' => (float) $this->stock,
            'min_stock' => (float) $this->min_stock,
            'is_low_stock' => $this->isLowStock(),
            'stock_packs' => $this->stockInPacks(),
            'unit' => $this->unit->value,
            'unit_label' => $this->unit->label(),
            'images' => $images,
            'image_url' => $images->first()['url'] ?? null,
            'is_active' => $this->is_active,
            'visibility' => $this->visibility->value,
            'visibility_label' => $this->visibility->label(),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'types' => ProductTypeResource::collection($this->whenLoaded('types')),
            'is_favorite' => is_array($favoriteIds) ? in_array($this->id, $favoriteIds, true) : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
