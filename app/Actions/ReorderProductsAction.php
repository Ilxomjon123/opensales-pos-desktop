<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Mahsulotlarni mini app ko'rinishi uchun qayta tartibga soladi.
 *
 * Kirish: dealer'ga tegishli mahsulot ID'lari massivi (yangi tartibda).
 * Berilgan ID'lar 1, 2, 3 ... ketma-ket sort_order oladi.
 * Ro'yxatga tushmagan boshqa mahsulotlar joriy nisbatlarini saqlab,
 * berilganlardan keyinga (N+1, N+2 ...) suriladi — image reorder bilan
 * bir xil pattern (ProductImageService::reorder).
 */
final class ReorderProductsAction
{
    /**
     * @param  list<int>  $orderedIds  Mahsulot ID'lari yangi tartibda.
     */
    public function execute(int $dealerId, array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        DB::transaction(function () use ($dealerId, $orderedIds): void {
            // Faqat shu dealer'ga tegishli ID'larni qabul qilamiz.
            $validIds = Product::query()
                ->where('dealer_id', $dealerId)
                ->whereIn('id', $orderedIds)
                ->pluck('id')
                ->all();

            $validSet = array_flip($validIds);

            $position = 1;
            foreach ($orderedIds as $id) {
                if (! isset($validSet[$id])) {
                    continue;
                }

                Product::query()
                    ->where('id', $id)
                    ->update(['sort_order' => $position]);

                $position++;
            }

            // Qolgan mahsulotlar — joriy sort_order tartibida pastga suriladi.
            $remaining = Product::query()
                ->where('dealer_id', $dealerId)
                ->whereNotIn('id', array_keys($validSet))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id');

            foreach ($remaining as $id) {
                Product::query()
                    ->where('id', $id)
                    ->update(['sort_order' => $position]);

                $position++;
            }
        });
    }
}
