<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Profit/Margin hisoboti.
 *
 * Tannarx manbai — `order_items.unit_cost` (sotuv paytidagi snapshot).
 * Sotuv paytida product/type.cost_price'dan snapshot qilinadi. Keyin tannarx
 * o'zgarsa eski hisobotlar buzilmaydi — har order_item o'z snapshot'iga ega.
 *
 * Period ichidagi har mahsulot uchun:
 *   revenue = SUM(order_items.delivered_qty × price) — fulfilled buyurtmalardan
 *   cogs = SUM(order_items.delivered_qty × COALESCE(unit_cost, 0))
 *   profit = revenue - cogs
 *   margin = profit / revenue × 100  (revenue > 0 va cogs > 0 bo'lsa)
 *
 * has_cost: kamida bitta order_item da unit_cost NOT NULL bo'lsa true.
 * Agar mahsulot uchun barcha snapshot'lar NULL bo'lsa (tannarx kiritilmagan),
 * `has_cost: false` va marja hisoblanmaydi.
 *
 * @phpstan-type ProfitFilters array{
 *     date_from?: string, date_to?: string,
 *     category_id?: ?int, product_id?: ?int,
 * }
 */
final class ProfitReportService
{
    /**
     * @param  ProfitFilters  $filters
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

        $totalRevenue = array_sum(array_map(fn ($r) => (int) $r['revenue'], $rows));
        $totalCogs = array_sum(array_map(fn ($r) => (int) $r['cogs'], $rows));
        $totalProfit = $totalRevenue - $totalCogs;

        $summary = [
            'products' => count($rows),
            'revenue' => $totalRevenue,
            'cogs' => $totalCogs,
            'profit' => $totalProfit,
            'margin' => $totalRevenue > 0 ? round($totalProfit / $totalRevenue * 100, 2) : 0.0,
            'products_without_cost' => count(array_filter($rows, fn ($r) => ! $r['has_cost'])),
        ];

        return [
            'summary' => $summary,
            'rows' => $rows,
            'meta' => $normalized,
        ];
    }

    /**
     * @param  ProfitFilters  $filters
     * @return iterable<int, list<string|int|float|null>>
     */
    public function exportRows(int $dealerId, array $filters): iterable
    {
        foreach ($this->generate($dealerId, $filters)['rows'] as $row) {
            yield [
                $row['name'],
                $row['category'] ?? '—',
                $row['delivered_qty'],
                $row['avg_price'],
                $row['avg_cost'],
                $row['revenue'],
                $row['cogs'],
                $row['profit'],
                $row['margin'],
                $row['has_cost'] ? 'ha' : 'yo\'q',
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

        // Period ichidagi har mahsulot bo'yicha: revenue (delivered_qty × price),
        // cogs (delivered_qty × unit_cost snapshot), va snapshot'i bor qtyning ulushi.
        $salesQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.dealer_id', $dealerId)
            ->whereIn('orders.status', [OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value])
            ->whereBetween('orders.created_at', [$from, $to])
            ->select([
                'order_items.product_id',
                DB::raw('COALESCE(SUM(order_items.delivered_qty), 0) as qty'),
                DB::raw('COALESCE(SUM(order_items.delivered_qty * order_items.price), 0) as revenue'),
                DB::raw('COALESCE(SUM(order_items.delivered_qty * COALESCE(order_items.unit_cost, 0)), 0) as cogs'),
                DB::raw('COALESCE(SUM(CASE WHEN order_items.unit_cost IS NOT NULL THEN order_items.delivered_qty ELSE 0 END), 0) as qty_with_cost'),
            ])
            ->groupBy('order_items.product_id');

        if ($filters['product_id'] !== null) {
            $salesQuery->where('order_items.product_id', $filters['product_id']);
        }

        if ($filters['category_id'] !== null) {
            $salesQuery->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('products.category_id', $filters['category_id']);
        }

        $sales = $salesQuery->get()->keyBy('product_id');

        if ($sales->isEmpty()) {
            return [];
        }

        $productIds = $sales->keys()->all();

        $products = Product::query()
            ->forDealer($dealerId)
            ->with('category:id,name')
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        $rows = $products->map(function (Product $p) use ($sales): array {
            $s = $sales[$p->id] ?? null;
            $qty = $s !== null ? (float) $s->qty : 0.0;
            $revenue = $s !== null ? (int) round((float) $s->revenue) : 0;
            $cogs = $s !== null ? (int) round((float) $s->cogs) : 0;
            $qtyWithCost = $s !== null ? (float) $s->qty_with_cost : 0.0;
            $hasCost = $qtyWithCost > 0 && $cogs > 0;
            $profit = $revenue - $cogs;
            $avgPrice = $qty > 0 ? (int) round($revenue / $qty) : 0;
            $avgCost = $qtyWithCost > 0 ? (int) round($cogs / $qtyWithCost) : 0;

            return [
                'id' => $p->id,
                'name' => (string) $p->name,
                'category' => $p->category?->name,
                'delivered_qty' => $qty,
                'avg_price' => $avgPrice,
                'avg_cost' => $avgCost,
                'revenue' => $revenue,
                'cogs' => $cogs,
                'profit' => $profit,
                'margin' => $revenue > 0 && $hasCost ? round($profit / $revenue * 100, 2) : 0.0,
                'has_cost' => $hasCost,
            ];
        });

        return $rows->sortByDesc('profit')->values()->all();
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
