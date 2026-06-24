<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Narxlarni ommaviy yangilash — ikki usul:
 *  1) Scope bo'yicha foizli / qiymatli tuzatish (oshirish / kamaytirish)
 *  2) CSV import — `id,price` formati
 */
final class BulkPriceService
{
    /**
     * @param  array{scope: 'all'|'category', category_id?: int|null, mode: 'percent'|'amount', value: int|float, direction: 'up'|'down'}  $params
     * @return array{updated: int, preview: list<array{id: int, name: string, old_price: float, new_price: float}>}
     */
    public function adjust(int $dealerId, array $params, bool $dryRun = false, int $previewLimit = 10): array
    {
        $query = $this->scopeQuery($dealerId, $params);

        $sign = $params['direction'] === 'up' ? 1 : -1;
        $value = max(0.0, (float) $params['value']);

        // Oldindan ko'rish uchun birinchi N ta
        $preview = (clone $query)->orderBy('name')->limit($previewLimit)
            ->get(['id', 'name', 'price'])
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'old_price' => (float) $p->price,
                'new_price' => $this->applyDelta((float) $p->price, $params['mode'], $value, $sign),
            ])
            ->all();

        if ($dryRun) {
            return ['updated' => 0, 'preview' => $preview];
        }

        $updated = DB::transaction(function () use ($query, $params, $sign, $value): int {
            if ($params['mode'] === 'percent') {
                $factor = 1 + ($sign * $value / 100);
                $expr = "ROUND(CAST(price AS NUMERIC) * {$factor}, 2)";

                return $query->update([
                    'price' => DB::raw("CASE WHEN {$expr} < 0 THEN 0 ELSE {$expr} END"),
                ]);
            }

            $delta = $sign * $value;

            return $query->update([
                'price' => DB::raw("CASE WHEN price + ({$delta}) < 0 THEN 0 ELSE ROUND(CAST(price + ({$delta}) AS NUMERIC), 2) END"),
            ]);
        });

        return ['updated' => $updated, 'preview' => $preview];
    }

    /**
     * CSV: sarlavha qatori majburiy. Ustunlar: `id`, `price`.
     *
     * @return array{updated: int, skipped: list<array{line: int, reason: string, value: string}>}
     */
    public function importCsv(int $dealerId, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return ['updated' => 0, 'skipped' => [['line' => 0, 'reason' => 'file_unreadable', 'value' => '']]];
        }

        try {
            $header = $this->readHeader($handle);

            if ($header === null) {
                return ['updated' => 0, 'skipped' => [['line' => 1, 'reason' => 'invalid_header', 'value' => '']]];
            }

            [$keyIndex, $priceIndex] = $header;

            $updated = 0;
            $skipped = [];
            $line = 1;

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $line++;

                if ($row === [null] || $row === []) {
                    continue;
                }

                $keyRaw = trim((string) ($row[$keyIndex] ?? ''));
                $priceRaw = trim((string) ($row[$priceIndex] ?? ''));

                if ($keyRaw === '' || ! is_numeric($priceRaw)) {
                    $skipped[] = ['line' => $line, 'reason' => 'invalid_row', 'value' => $keyRaw];

                    continue;
                }

                $newPrice = max(0.0, round((float) $priceRaw, 2));

                $affected = Product::query()
                    ->forDealer($dealerId)
                    ->whereKey((int) $keyRaw)
                    ->update(['price' => $newPrice]);

                if ($affected === 0) {
                    $skipped[] = ['line' => $line, 'reason' => 'not_found', 'value' => $keyRaw];
                } else {
                    $updated += $affected;
                }
            }

            return ['updated' => $updated, 'skipped' => $skipped];
        } finally {
            fclose($handle);
        }
    }

    private function applyDelta(float $current, string $mode, float $value, int $sign): float
    {
        if ($mode === 'percent') {
            $factor = 1 + ($sign * $value / 100);

            return max(0.0, round($current * $factor, 2));
        }

        return max(0.0, round($current + ($sign * $value), 2));
    }

    /**
     * @return array{0: int, 1: int}|null [keyIndex, priceIndex]
     */
    private function readHeader($handle): ?array
    {
        $row = fgetcsv($handle, 0, ',', '"', '\\');

        if ($row === false || $row === null) {
            return null;
        }

        // BOM olib tashlash
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        $normalized = array_map(static fn ($c): string => strtolower(trim((string) $c)), $row);

        $priceIdx = array_search('price', $normalized, true);

        if ($priceIdx === false) {
            return null;
        }

        $idIdx = array_search('id', $normalized, true);
        if ($idIdx !== false) {
            return [(int) $idIdx, (int) $priceIdx];
        }

        return null;
    }

    /**
     * @param  array{scope: string, category_id?: int|null}  $params
     */
    private function scopeQuery(int $dealerId, array $params): Builder
    {
        $query = Product::query()->forDealer($dealerId);

        if ($params['scope'] === 'category' && ! empty($params['category_id'])) {
            $query->where('category_id', (int) $params['category_id']);
        }

        return $query;
    }
}
