<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ProductType
 */
final class ProductTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->relationLoaded('images')
            ? $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => Storage::url($img->path),
                'thumb_url' => $img->thumb_path !== null ? Storage::url($img->thumb_path) : Storage::url($img->path),
                'sort_order' => $img->sort_order,
            ])->values()
            : collect();

        $packSize = max(1.0, (float) $this->pack_size);
        $packPrice = $this->pack_price !== null
            ? (float) $this->pack_price
            : round((float) $this->price * $packSize, 2);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'pack_size' => $packSize,
            'pack_price' => $packPrice,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'pack_cost_price' => $this->pack_cost_price !== null ? (float) $this->pack_cost_price : null,
            'bulk_only' => (bool) $this->bulk_only,
            'stock' => (float) $this->stock,
            'min_stock' => $this->min_stock !== null ? (float) $this->min_stock : null,
            'is_low_stock' => $this->isLowStock(),
            'stock_packs' => $this->stockInPacks(),
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'images' => $images,
            'image_url' => $images->first()['url'] ?? null,
        ];
    }
}
