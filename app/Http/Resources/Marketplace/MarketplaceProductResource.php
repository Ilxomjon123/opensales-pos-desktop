<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use App\Enums\Currency;
use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Birja mahsuloti — diller boshqa dillerlardan zakas berishi uchun.
 * Mini app katalogi bilan bir xil shakl: images[], types[], paket narxi,
 * bulk_only. FARQ: birjada do'kon promotsiyalari QO'LLANMAYDI (ular faqat
 * do'konchilar uchun), shuning uchun discount maydonlari bo'sh; stock esa
 * KO'RSATILADI (diller xaridor uchun muhim).
 *
 * @mixin Product
 */
final class MarketplaceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->relationLoaded('images')
            ? $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => Storage::url($img->path),
                'sort_order' => $img->sort_order,
            ])->values()
            : collect();

        $firstImage = $this->relationLoaded('images') ? $this->images->first() : null;
        $packSize = max(1.0, (float) $this->pack_size);

        $hasTypes = (bool) $this->has_types;
        $types = $this->relationLoaded('activeTypes')
            ? $this->activeTypes
            : ($this->relationLoaded('types') ? $this->types->where('is_active', true) : collect());

        $basePackPrice = $this->pack_price !== null
            ? (float) $this->pack_price
            : round((float) $this->price * $packSize, 2);

        $startingPrice = $hasTypes && $types->isNotEmpty()
            ? (float) $types->map(fn ($t) => (float) $t->price)->min()
            : (float) $this->price;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'original_price' => null,
            'discount_percent' => 0,
            'pack_size' => $packSize,
            'pack_price' => $basePackPrice,
            'original_pack_price' => null,
            'bulk_only' => (bool) $this->bulk_only,
            'has_types' => $hasTypes,
            'starting_price' => $startingPrice,
            'unit' => $this->unit->value,
            'unit_label' => $this->unit->label(),
            // Valyuta belgisi sotuvchidan olinadi (birja ko'p valyutali bo'lishi mumkin).
            'currency' => $this->whenLoaded('dealer', fn () => ($this->dealer->currency ?? Currency::UZS)->symbol()),
            'stock' => (float) $this->stock,
            'images' => $images,
            'image_url' => $firstImage !== null
                ? Storage::url($firstImage->thumb_path ?? $firstImage->path)
                : null,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category !== null ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'types' => $hasTypes
                ? $types->values()->map(fn ($t) => $this->typeArray($t))->all()
                : [],
            'seller' => $this->whenLoaded('dealer', fn () => [
                'id' => $this->dealer->id,
                'name' => $this->dealer->name,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function typeArray(ProductType $type): array
    {
        $packSize = max(1.0, (float) $type->pack_size);
        $firstImage = $type->relationLoaded('images') ? $type->images->first() : null;

        return [
            'id' => $type->id,
            'product_id' => $type->product_id,
            'name' => $type->name,
            'price' => (float) $type->price,
            'original_price' => null,
            'pack_size' => $packSize,
            'pack_price' => $type->pack_price !== null
                ? (float) $type->pack_price
                : round((float) $type->price * $packSize, 2),
            'original_pack_price' => null,
            'bulk_only' => (bool) $type->bulk_only,
            'sort_order' => (int) $type->sort_order,
            'is_active' => (bool) $type->is_active,
            'stock' => (float) $type->stock,
            'image_url' => $firstImage !== null
                ? Storage::url($firstImage->path)
                : null,
        ];
    }
}
