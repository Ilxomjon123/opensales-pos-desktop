<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ProductImageService
{
    private const DISK = 'public';

    private const DIRECTORY = 'products';

    public function __construct(
        private readonly ImageOptimizer $optimizer,
    ) {}

    private static function directoryFor(int $dealerId): string
    {
        return "dealers/{$dealerId}/".self::DIRECTORY;
    }

    /**
     * Mahsulot yoki mahsulot tipiga rasm yuklash. $type berilsa rasm tipga
     * biriktiriladi, bo'lmasa product darajasida saqlanadi.
     *
     * @param  list<UploadedFile>  $files
     * @return array<int, ProductImage>
     */
    public function attachMany(Product $product, array $files, int $startSortOrder = 0, ?ProductType $type = null): array
    {
        $created = [];

        foreach (array_values($files) as $idx => $file) {
            $created[$idx] = $this->attach($product, $type?->id, $file, $startSortOrder + $idx);
        }

        return $created;
    }

    /**
     * Berilgan ID lar bo'yicha rasmlarni (storage + DB) o'chirish.
     * Faqat berilgan product/type doirasidagi rasmlar o'chiriladi.
     *
     * @param  array<int, int|string>  $imageIds
     */
    public function detachMany(int $productId, array $imageIds, ?int $productTypeId = null): void
    {
        if ($imageIds === []) {
            return;
        }

        $images = ProductImage::query()
            ->where('product_id', $productId)
            ->when(
                $productTypeId !== null,
                fn ($q) => $q->where('product_type_id', $productTypeId),
                fn ($q) => $q->whereNull('product_type_id'),
            )
            ->whereIn('id', $imageIds)
            ->get();

        foreach ($images as $image) {
            Storage::disk(self::DISK)->delete($image->path);
            $image->delete();
        }
    }

    /**
     * Mahsulotning barcha rasmlarini storage dan o'chirish (model o'chirilishidan oldin).
     * Type rasmlari ham — chunki cascade orqali DB dan ham o'chiriladi.
     */
    public function detachAll(Product $product): void
    {
        $product->loadMissing('allImages');

        foreach ($product->allImages as $image) {
            Storage::disk(self::DISK)->delete($image->path);
        }
    }

    public function detachAllForType(ProductType $type): void
    {
        $type->loadMissing('images');

        foreach ($type->images as $image) {
            Storage::disk(self::DISK)->delete($image->path);
            $image->delete();
        }
    }

    /**
     * Rasmlar tartibini image_order tokenlari bo'yicha qayta joylashtiradi.
     * Tokenlar: "ex:<id>" — mavjud rasm, "new:<idx>" — yangi yuklangan rasm indeksi.
     *
     * @param  list<string>  $order
     * @param  array<int, ProductImage>  $newImages  upload indeksi => yaratilgan rasm
     */
    public function reorder(int $productId, array $order, array $newImages = [], ?int $productTypeId = null): void
    {
        if ($order === []) {
            return;
        }

        $orderedIds = $this->resolveOrderedIds($order, $newImages);

        $validIds = ProductImage::query()
            ->where('product_id', $productId)
            ->when(
                $productTypeId !== null,
                fn ($q) => $q->where('product_type_id', $productTypeId),
                fn ($q) => $q->whereNull('product_type_id'),
            )
            ->whereIn('id', $orderedIds)
            ->pluck('id')
            ->all();
        $validIdsSet = array_flip($validIds);

        $sortOrder = 0;
        foreach ($orderedIds as $id) {
            if (! isset($validIdsSet[$id])) {
                continue;
            }

            ProductImage::query()
                ->where('id', $id)
                ->update(['sort_order' => $sortOrder]);

            $sortOrder++;
        }

        $remainingQuery = ProductImage::query()
            ->where('product_id', $productId)
            ->when(
                $productTypeId !== null,
                fn ($q) => $q->where('product_type_id', $productTypeId),
                fn ($q) => $q->whereNull('product_type_id'),
            )
            ->whereNotIn('id', $orderedIds)
            ->orderBy('sort_order')
            ->orderBy('id');

        $remaining = $remainingQuery->pluck('id');

        foreach ($remaining as $id) {
            ProductImage::query()
                ->where('id', $id)
                ->update(['sort_order' => $sortOrder]);

            $sortOrder++;
        }
    }

    public function currentMaxSortOrder(int $productId, ?int $productTypeId = null): int
    {
        return (int) ProductImage::query()
            ->where('product_id', $productId)
            ->when(
                $productTypeId !== null,
                fn ($q) => $q->where('product_type_id', $productTypeId),
                fn ($q) => $q->whereNull('product_type_id'),
            )
            ->max('sort_order');
    }

    private function attach(Product $product, ?int $productTypeId, UploadedFile $file, int $sortOrder): ProductImage
    {
        $encoded = $this->optimizer->encodeWithThumb($file->getRealPath());

        $directory = self::directoryFor((int) $product->dealer_id);
        $base = bin2hex(random_bytes(20));
        $path = $directory.'/'.$base.'.'.ImageOptimizer::EXTENSION;
        $thumbPath = $directory.'/'.$base.'_thumb.'.ImageOptimizer::EXTENSION;

        Storage::disk(self::DISK)->put($path, $encoded['full']);
        Storage::disk(self::DISK)->put($thumbPath, $encoded['thumb']);

        return ProductImage::query()->create([
            'product_id' => $product->id,
            'product_type_id' => $productTypeId,
            'path' => $path,
            'thumb_path' => $thumbPath,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  list<string>  $order
     * @param  array<int, ProductImage>  $newImages
     * @return list<int>
     */
    private function resolveOrderedIds(array $order, array $newImages): array
    {
        $ids = [];

        foreach ($order as $token) {
            if (str_starts_with($token, 'ex:')) {
                $ids[] = (int) substr($token, 3);

                continue;
            }

            if (str_starts_with($token, 'new:')) {
                $idx = (int) substr($token, 4);
                if (isset($newImages[$idx])) {
                    $ids[] = $newImages[$idx]->id;
                }
            }
        }

        return $ids;
    }
}
