<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\TransactionType;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Inventarizatsiya hisoboti — period bo'yicha mahsulot harakati.
 *
 * Har mahsulot uchun:
 *  - period boshidagi qoldiq (current_stock'dan period oxirigacha bo'lgan deltalarni ayirib chiqarish)
 *  - period ichidagi prixod (STOCK_IN) va mijozdan vozvrat (SHOP_RETURN) — stok ortishi
 *  - period ichidagi chiqim (STOCK_OUT, buyurtmalar) va ta'minotchiga vozvrat (SUPPLIER_RETURN)
 *  - period ichidagi tuzatish (STOCK_ADJUST) — stock_after - stock_before yig'indisi
 *  - joriy qoldiq (products.stock)
 *  - sof o'zgarish va period oxiridagi (taxminiy) qoldiq
 *
 * Boshlang'ich qoldiq formulasi:
 *   period_end_stock = current_stock - sum(delta_after_period)
 *   period_start_stock = period_end_stock - net_change_in_period
 * delta = stock_after - stock_before har transaction_detail uchun.
 *
 * @phpstan-type InventoryFilters array{
 *     date_from?: string, date_to?: string,
 *     category_id?: ?int, product_id?: ?int,
 * }
 */
final class InventoryReportService
{
    /**
     * @param  InventoryFilters  $filters
     * @return array{
     *     summary: array<string,int|float>,
     *     rows: list<array<string,mixed>>,
     *     meta: array<string,mixed>,
     * }
     */
    public function generate(int $dealerId, array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $rows = $this->rows($dealerId, $normalized);

        $summary = [
            'products' => count($rows),
            'in_qty' => array_sum(array_map(fn ($r) => (float) $r['in_qty'], $rows)),
            'out_qty' => array_sum(array_map(fn ($r) => (float) $r['out_qty'], $rows)),
            'shop_return_qty' => array_sum(array_map(fn ($r) => (float) $r['shop_return_qty'], $rows)),
            'supplier_return_qty' => array_sum(array_map(fn ($r) => (float) $r['supplier_return_qty'], $rows)),
            'adjust_delta' => array_sum(array_map(fn ($r) => (float) $r['adjust_delta'], $rows)),
            'net_change' => array_sum(array_map(fn ($r) => (float) $r['net_change'], $rows)),
            'current_stock' => array_sum(array_map(fn ($r) => (float) $r['current_stock'], $rows)),
        ];

        return [
            'summary' => $summary,
            'rows' => $rows,
            'meta' => $normalized,
        ];
    }

    /**
     * @param  InventoryFilters  $filters
     * @return iterable<int, list<string|int|float|null>>
     */
    public function exportRows(int $dealerId, array $filters): iterable
    {
        foreach ($this->generate($dealerId, $filters)['rows'] as $row) {
            yield [
                $row['name'],
                $row['category'] ?? '—',
                $row['unit'],
                $row['start_stock'],
                $row['in_qty'],
                $row['shop_return_qty'],
                $row['out_qty'],
                $row['supplier_return_qty'],
                $row['adjust_delta'],
                $row['net_change'],
                $row['end_stock'],
                $row['current_stock'],
            ];
        }
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string,
     *     category_id: ?int, product_id: ?int,
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function rows(int $dealerId, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        // Period ichidagi harakat — har mahsulot bo'yicha, har transaction tipi bilan.
        $movementsInPeriod = $this->aggregateMovements($dealerId, $from, $to, $filters);

        // Period oxiridan keyingi deltalar — period_end qoldig'ini hisoblash uchun.
        $deltasAfter = $this->aggregateDeltas($dealerId, $to, null, $filters);

        $productQuery = Product::query()
            ->forDealer($dealerId)
            ->with('category:id,name')
            ->orderBy('name');

        if ($filters['category_id'] !== null) {
            $productQuery->where('category_id', $filters['category_id']);
        }

        if ($filters['product_id'] !== null) {
            $productQuery->where('id', $filters['product_id']);
        }

        $products = $productQuery->limit(2000)->get(['id', 'name', 'unit', 'stock', 'category_id']);

        $rows = $products->map(function (Product $p) use ($movementsInPeriod, $deltasAfter): array {
            $key = $p->id;
            $m = $movementsInPeriod[$key] ?? [
                'in_qty' => 0.0, 'out_qty' => 0.0,
                'shop_return_qty' => 0.0, 'supplier_return_qty' => 0.0,
                'adjust_delta' => 0.0,
            ];

            $deltaAfter = (float) ($deltasAfter[$key] ?? 0);
            $current = (float) $p->stock;
            $endStock = $current - $deltaAfter;

            $netChange = (float) $m['in_qty']
                + (float) $m['shop_return_qty']
                - (float) $m['out_qty']
                - (float) $m['supplier_return_qty']
                + (float) $m['adjust_delta'];

            $startStock = $endStock - $netChange;

            return [
                'id' => $p->id,
                'name' => (string) $p->name,
                'category' => $p->category?->name,
                'unit' => $p->unit->value,
                'start_stock' => $startStock,
                'in_qty' => (float) $m['in_qty'],
                'out_qty' => (float) $m['out_qty'],
                'shop_return_qty' => (float) $m['shop_return_qty'],
                'supplier_return_qty' => (float) $m['supplier_return_qty'],
                'adjust_delta' => (float) $m['adjust_delta'],
                'net_change' => $netChange,
                'end_stock' => $endStock,
                'current_stock' => $current,
            ];
        });

        // Faqat harakat bo'lgan yoki current_stock != 0 mahsulotlarni ko'rsatamiz.
        return $rows->filter(function (array $r): bool {
            return abs($r['net_change']) > 0
                || abs($r['current_stock']) > 0
                || abs($r['start_stock']) > 0;
        })->values()->all();
    }

    /**
     * Har product_id uchun harakat tipiga qarab miqdor agregati.
     *
     * @param  array{
     *     date_from: string, date_to: string,
     *     category_id: ?int, product_id: ?int,
     * }  $filters
     * @return array<int, array{in_qty: float, out_qty: float, shop_return_qty: float, supplier_return_qty: float, adjust_delta: float}>
     */
    private function aggregateMovements(int $dealerId, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $query = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transactions.dealer_id', $dealerId)
            ->whereBetween('transactions.created_at', [$from, $to])
            ->select([
                'transaction_details.product_id',
                'transactions.type',
                DB::raw('COALESCE(SUM(transaction_details.qty), 0) as qty_sum'),
                DB::raw('COALESCE(SUM(COALESCE(transaction_details.stock_after, 0) - COALESCE(transaction_details.stock_before, 0)), 0) as delta_sum'),
            ])
            ->groupBy('transaction_details.product_id', 'transactions.type');

        if ($filters['product_id'] !== null) {
            $query->where('transaction_details.product_id', $filters['product_id']);
        }

        if ($filters['category_id'] !== null) {
            $query->join('products', 'products.id', '=', 'transaction_details.product_id')
                ->where('products.category_id', $filters['category_id']);
        }

        $rows = $query->get();

        $out = [];

        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            $out[$pid] ??= [
                'in_qty' => 0.0, 'out_qty' => 0.0,
                'shop_return_qty' => 0.0, 'supplier_return_qty' => 0.0,
                'adjust_delta' => 0.0,
            ];

            $key = match ($row->type) {
                TransactionType::STOCK_IN->value => 'in_qty',
                TransactionType::STOCK_OUT->value => 'out_qty',
                TransactionType::SHOP_RETURN->value => 'shop_return_qty',
                TransactionType::SUPPLIER_RETURN->value => 'supplier_return_qty',
                TransactionType::STOCK_ADJUST->value => 'adjust_delta',
                default => null,
            };

            if ($key === null) {
                continue;
            }

            $out[$pid][$key] = $key === 'adjust_delta' ? (float) $row->delta_sum : (float) $row->qty_sum;
        }

        return $out;
    }

    /**
     * Berilgan vaqtdan keyingi (yoki oraliqdagi) net delta — har product uchun.
     *
     * @param  array{
     *     date_from: string, date_to: string,
     *     category_id: ?int, product_id: ?int,
     * }  $filters
     * @return array<int, float>
     */
    private function aggregateDeltas(int $dealerId, CarbonImmutable $from, ?CarbonImmutable $to, array $filters): array
    {
        $query = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transactions.dealer_id', $dealerId)
            ->where('transactions.created_at', '>', $from)
            ->select([
                'transaction_details.product_id',
                DB::raw('COALESCE(SUM(COALESCE(transaction_details.stock_after, 0) - COALESCE(transaction_details.stock_before, 0)), 0) as delta_sum'),
            ])
            ->groupBy('transaction_details.product_id');

        if ($to !== null) {
            $query->where('transactions.created_at', '<=', $to);
        }

        if ($filters['product_id'] !== null) {
            $query->where('transaction_details.product_id', $filters['product_id']);
        }

        if ($filters['category_id'] !== null) {
            $query->join('products', 'products.id', '=', 'transaction_details.product_id')
                ->where('products.category_id', $filters['category_id']);
        }

        return $query->pluck('delta_sum', 'product_id')
            ->map(fn ($v): float => (float) $v)
            ->all();
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{
     *     date_from: string, date_to: string,
     *     category_id: ?int, product_id: ?int,
     * }
     */
    private function normalizeFilters(array $filters): array
    {
        $dateFrom = isset($filters['date_from']) && $filters['date_from'] !== ''
            ? (string) $filters['date_from']
            : CarbonImmutable::now()->subDays(29)->format('Y-m-d');

        $dateTo = isset($filters['date_to']) && $filters['date_to'] !== ''
            ? (string) $filters['date_to']
            : CarbonImmutable::now()->format('Y-m-d');

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'category_id' => isset($filters['category_id']) && $filters['category_id'] !== '' ? (int) $filters['category_id'] : null,
            'product_id' => isset($filters['product_id']) && $filters['product_id'] !== '' ? (int) $filters['product_id'] : null,
        ];
    }
}
