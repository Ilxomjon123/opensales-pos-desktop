<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductType;
use App\Services\ProductImageService;
use Illuminate\Http\Request;

/**
 * Mahsulot tiplarini (variantlarini) bitta requestdan sinxronlaydi:
 *  - mavjud tiplar (id berilgan) — yangilanadi
 *  - id siz qator — yangi tip yaratiladi
 *  - removed_type_ids dagilar (tegishli rasmlar bilan) o'chiriladi
 *  - har tip uchun rasm CRUD (attach/detach/reorder) ProductImageService orqali
 *
 * NOTE: bu chaqiruv DB tranzaksiya ichida bo'lishi taxmin qilinadi.
 */
final class SyncProductTypesAction
{
    public function __construct(private readonly ProductImageService $images) {}

    /**
     * @param  array<int, array<string, mixed>>  $typeRows
     * @param  array<int, int>  $removedTypeIds
     */
    public function execute(Product $product, array $typeRows, array $removedTypeIds, Request $request): void
    {
        if ($removedTypeIds !== []) {
            $toDelete = ProductType::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $removedTypeIds)
                ->get();

            foreach ($toDelete as $type) {
                $this->images->detachAllForType($type);
                $type->delete();
            }
        }

        foreach ($typeRows as $idx => $row) {
            $typeId = isset($row['id']) ? (int) $row['id'] : 0;

            $payload = [
                'name' => (string) $row['name'],
                'price' => (float) $row['price'],
                'pack_price' => isset($row['pack_price']) && $row['pack_price'] !== '' && $row['pack_price'] !== null
                    ? (float) $row['pack_price']
                    : null,
                'cost_price' => isset($row['cost_price']) && $row['cost_price'] !== '' && $row['cost_price'] !== null
                    ? (float) $row['cost_price']
                    : null,
                'pack_cost_price' => isset($row['pack_cost_price']) && $row['pack_cost_price'] !== '' && $row['pack_cost_price'] !== null
                    ? (float) $row['pack_cost_price']
                    : null,
                'stock' => (float) $row['stock'],
                'min_stock' => isset($row['min_stock']) && $row['min_stock'] !== '' ? (float) $row['min_stock'] : null,
                'pack_size' => max(1.0, (float) $row['pack_size']),
                'bulk_only' => (bool) ($row['bulk_only'] ?? false),
                'sort_order' => (int) ($row['sort_order'] ?? $idx),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($typeId > 0) {
                $type = ProductType::query()
                    ->where('product_id', $product->id)
                    ->where('id', $typeId)
                    ->first();

                if ($type === null) {
                    continue;
                }

                $type->update($payload);
            } else {
                $type = ProductType::query()->create([
                    'product_id' => $product->id,
                    ...$payload,
                ]);
            }

            $removeImageIds = (array) ($row['remove_image_ids'] ?? []);
            $this->images->detachMany($product->id, $removeImageIds, $type->id);

            $files = $request->file("types.{$idx}.images") ?? [];
            $startSortOrder = $this->images->currentMaxSortOrder($product->id, $type->id) + 1;
            $newImages = $this->images->attachMany($product, $files, $startSortOrder, $type);

            $imageOrder = (array) ($row['image_order'] ?? []);
            $this->images->reorder(
                productId: $product->id,
                order: $imageOrder,
                newImages: $newImages,
                productTypeId: $type->id,
            );
        }
    }
}
