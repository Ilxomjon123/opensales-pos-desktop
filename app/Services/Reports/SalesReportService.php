<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sotuv hisoboti — daterange + filter + group by bo'yicha agregat.
 *
 * Asosiy qoidalar:
 * - Revenue manbai: orders.delivered_total (fulfilled holatlar uchun snapshot).
 *   `total` emas, chunki delivered_total — qatnashgan miqdor asosida hisoblangan.
 * - Net revenue = delivered_total - discount (order darajasida).
 * - Statusga ko'ra agregat ikki yo'l bilan: "fulfilled" (DELIVERED+RECEIVED, default)
 *   yoki barcha statuslar (foydalanuvchi tanlaganda).
 * - Mahsulot/kategoriya kesimida order_items.delivered_qty × price ishlatamiz
 *   (chegirma chiziq darajasida taqsimlanmaydi — order summasida ko'rsatiladi).
 *
 * @phpstan-type SalesFilters array{
 *     date_from: string,
 *     date_to: string,
 *     group_by: string,
 *     shop_id?: int|null,
 *     deliveryman_id?: int|null,
 *     category_id?: int|null,
 *     statuses?: list<string>,
 * }
 */
final class SalesReportService
{
    public const GROUP_DAY = 'day';

    public const GROUP_WEEK = 'week';

    public const GROUP_MONTH = 'month';

    public const GROUP_SHOP = 'shop';

    public const GROUP_DELIVERYMAN = 'deliveryman';

    public const GROUP_CATEGORY = 'category';

    public const GROUP_PRODUCT = 'product';

    public const GROUP_BY_OPTIONS = [
        self::GROUP_DAY,
        self::GROUP_WEEK,
        self::GROUP_MONTH,
        self::GROUP_SHOP,
        self::GROUP_DELIVERYMAN,
        self::GROUP_CATEGORY,
        self::GROUP_PRODUCT,
    ];

    /**
     * @param  SalesFilters  $filters
     * @return array{
     *     summary: array<string,int|float>,
     *     rows: list<array<string,mixed>>,
     *     meta: array<string,mixed>,
     * }
     */
    public function generate(int $dealerId, array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        return [
            'summary' => $this->summary($dealerId, $normalized),
            'rows' => $this->rows($dealerId, $normalized),
            'meta' => [
                'group_by' => $normalized['group_by'],
                'date_from' => $normalized['date_from'],
                'date_to' => $normalized['date_to'],
                'statuses' => $normalized['statuses'],
            ],
        ];
    }

    /**
     * CSV eksport uchun row generator.
     *
     * @param  SalesFilters  $filters
     * @return iterable<int, list<string|int|float|null>>
     */
    public function exportRows(int $dealerId, array $filters): iterable
    {
        $normalized = $this->normalizeFilters($filters);

        foreach ($this->rows($dealerId, $normalized) as $row) {
            yield [
                $row['label'],
                $row['orders'] ?? 0,
                $row['qty'] ?? null,
                $row['gross'] ?? 0,
                $row['discount'] ?? 0,
                $row['net'] ?? 0,
                $row['aov'] ?? 0,
            ];
        }
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return array<string,int|float>
     */
    private function summary(int $dealerId, array $filters): array
    {
        $base = $this->ordersBaseQuery($dealerId, $filters);

        $agg = (clone $base)
            ->selectRaw('
                COUNT(*) as orders_count,
                COALESCE(SUM(delivered_total), 0) as gross,
                COALESCE(SUM(discount), 0) as discount,
                COALESCE(SUM(delivered_total - COALESCE(discount, 0)), 0) as net
            ')
            ->first();

        $orders = (int) ($agg->orders_count ?? 0);
        $gross = (int) ($agg->gross ?? 0);
        $discount = (int) ($agg->discount ?? 0);
        $net = (int) ($agg->net ?? 0);

        return [
            'orders' => $orders,
            'gross' => $gross,
            'discount' => $discount,
            'net' => $net,
            'aov' => $orders > 0 ? (int) round($net / $orders) : 0,
        ];
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function rows(int $dealerId, array $filters): array
    {
        return match ($filters['group_by']) {
            self::GROUP_DAY, self::GROUP_WEEK, self::GROUP_MONTH => $this->groupByPeriod($dealerId, $filters),
            self::GROUP_SHOP => $this->groupByShop($dealerId, $filters),
            self::GROUP_DELIVERYMAN => $this->groupByDeliveryman($dealerId, $filters),
            self::GROUP_CATEGORY => $this->groupByCategory($dealerId, $filters),
            self::GROUP_PRODUCT => $this->groupByProduct($dealerId, $filters),
            default => [],
        };
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function groupByPeriod(int $dealerId, array $filters): array
    {
        // DB-agnostik: kunlik agregatni olamiz va PHP da week/month bo'yicha guruhlaymiz.
        // Bu PostgreSQL/MySQL/SQLite uchun bir xil ishlaydi.
        $rows = $this->ordersBaseQuery($dealerId, $filters)
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(delivered_total), 0) as gross'),
                DB::raw('COALESCE(SUM(discount), 0) as discount'),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get();

        $buckets = [];

        foreach ($rows as $row) {
            $key = $this->bucketKey((string) $row->date, $filters['group_by']);
            $buckets[$key] ??= ['label' => $key, 'orders' => 0, 'gross' => 0, 'discount' => 0];
            $buckets[$key]['orders'] += (int) $row->orders;
            $buckets[$key]['gross'] += (int) $row->gross;
            $buckets[$key]['discount'] += (int) $row->discount;
        }

        // Bo'sh kunlar/oylarni ham ko'rsatamiz — chart uzluksiz bo'lishi uchun.
        $filled = $this->fillEmptyPeriods($filters['date_from'], $filters['date_to'], $filters['group_by'], $buckets);

        return array_values(array_map(function (array $b): array {
            $net = (int) $b['gross'] - (int) $b['discount'];
            $orders = (int) $b['orders'];

            return [
                'label' => $b['label'],
                'orders' => $orders,
                'gross' => (int) $b['gross'],
                'discount' => (int) $b['discount'],
                'net' => $net,
                'aov' => $orders > 0 ? (int) round($net / $orders) : 0,
            ];
        }, $filled));
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function groupByShop(int $dealerId, array $filters): array
    {
        $rows = $this->ordersBaseQuery($dealerId, $filters)
            ->join('shops', 'shops.id', '=', 'orders.shop_id')
            ->select([
                'orders.shop_id',
                DB::raw('MAX(shops.name) as label'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(orders.delivered_total), 0) as gross'),
                DB::raw('COALESCE(SUM(orders.discount), 0) as discount'),
            ])
            ->groupBy('orders.shop_id')
            ->orderByDesc(DB::raw('SUM(orders.delivered_total - COALESCE(orders.discount, 0))'))
            ->get();

        return $rows->map(function ($r): array {
            $orders = (int) $r->orders;
            $net = (int) $r->gross - (int) $r->discount;

            return [
                'id' => (int) $r->shop_id,
                'label' => (string) $r->label,
                'orders' => $orders,
                'gross' => (int) $r->gross,
                'discount' => (int) $r->discount,
                'net' => $net,
                'aov' => $orders > 0 ? (int) round($net / $orders) : 0,
            ];
        })->values()->all();
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function groupByDeliveryman(int $dealerId, array $filters): array
    {
        $rows = $this->ordersBaseQuery($dealerId, $filters)
            ->leftJoin('users', 'users.id', '=', 'orders.deliveryman_id')
            ->select([
                'orders.deliveryman_id',
                DB::raw('MAX(users.name) as label'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(orders.delivered_total), 0) as gross'),
                DB::raw('COALESCE(SUM(orders.discount), 0) as discount'),
            ])
            ->groupBy('orders.deliveryman_id')
            ->orderByDesc(DB::raw('SUM(orders.delivered_total - COALESCE(orders.discount, 0))'))
            ->get();

        return $rows->map(function ($r): array {
            $orders = (int) $r->orders;
            $net = (int) $r->gross - (int) $r->discount;

            return [
                'id' => $r->deliveryman_id !== null ? (int) $r->deliveryman_id : null,
                'label' => (string) ($r->label ?? '—'),
                'orders' => $orders,
                'gross' => (int) $r->gross,
                'discount' => (int) $r->discount,
                'net' => $net,
                'aov' => $orders > 0 ? (int) round($net / $orders) : 0,
            ];
        })->values()->all();
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function groupByCategory(int $dealerId, array $filters): array
    {
        $rows = $this->itemsBaseQuery($dealerId, $filters)
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->select([
                'products.category_id',
                DB::raw("MAX(COALESCE(product_categories.name, '—')) as label"),
                DB::raw('COALESCE(SUM(order_items.delivered_qty), 0) as qty'),
                DB::raw('COALESCE(SUM(order_items.delivered_qty * order_items.price), 0) as gross'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders'),
            ])
            ->groupBy('products.category_id')
            ->orderByDesc(DB::raw('SUM(order_items.delivered_qty * order_items.price)'))
            ->get();

        return $rows->map(function ($r): array {
            $gross = (int) round((float) $r->gross);
            $orders = (int) $r->orders;

            return [
                'id' => $r->category_id !== null ? (int) $r->category_id : null,
                'label' => (string) $r->label,
                'orders' => $orders,
                'qty' => (float) $r->qty,
                'gross' => $gross,
                'discount' => 0,
                'net' => $gross,
                'aov' => $orders > 0 ? (int) round($gross / $orders) : 0,
            ];
        })->values()->all();
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function groupByProduct(int $dealerId, array $filters): array
    {
        $rows = $this->itemsBaseQuery($dealerId, $filters)
            ->select([
                'order_items.product_id',
                DB::raw('MAX(order_items.product_name) as label'),
                DB::raw('COALESCE(SUM(order_items.delivered_qty), 0) as qty'),
                DB::raw('COALESCE(SUM(order_items.delivered_qty * order_items.price), 0) as gross'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders'),
            ])
            ->groupBy('order_items.product_id')
            ->orderByDesc(DB::raw('SUM(order_items.delivered_qty * order_items.price)'))
            ->limit(500)
            ->get();

        return $rows->map(function ($r): array {
            $gross = (int) round((float) $r->gross);
            $orders = (int) $r->orders;

            return [
                'id' => $r->product_id !== null ? (int) $r->product_id : null,
                'label' => (string) $r->label,
                'orders' => $orders,
                'qty' => (float) $r->qty,
                'gross' => $gross,
                'discount' => 0,
                'net' => $gross,
                'aov' => $orders > 0 ? (int) round($gross / $orders) : 0,
            ];
        })->values()->all();
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return Builder<Order>
     */
    private function ordersBaseQuery(int $dealerId, array $filters): Builder
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        return Order::query()
            ->forDealer($dealerId)
            ->shopChannel()
            ->whereIn('status', $filters['statuses'])
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['shop_id'] !== null, fn (Builder $q) => $q->where('shop_id', $filters['shop_id']))
            ->when($filters['deliveryman_id'] !== null, fn (Builder $q) => $q->where('deliveryman_id', $filters['deliveryman_id']));
    }

    /**
     * order_items kesimi uchun base: orders bilan join, filterlarni qo'llaymiz.
     *
     * @param  array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
     * }  $filters
     * @return Builder<OrderItem>
     */
    private function itemsBaseQuery(int $dealerId, array $filters): Builder
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.dealer_id', $dealerId)
            ->where('orders.channel', '!=', 'marketplace')
            ->whereIn('orders.status', $filters['statuses'])
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($filters['shop_id'] !== null, fn (Builder $q) => $q->where('orders.shop_id', $filters['shop_id']))
            ->when($filters['deliveryman_id'] !== null, fn (Builder $q) => $q->where('orders.deliveryman_id', $filters['deliveryman_id']));

        if ($filters['category_id'] !== null) {
            $query->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('products.category_id', $filters['category_id']);
        }

        return $query;
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{
     *     date_from: string, date_to: string, group_by: string,
     *     shop_id: ?int, deliveryman_id: ?int, category_id: ?int,
     *     statuses: list<string>
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

        $groupBy = isset($filters['group_by']) && in_array($filters['group_by'], self::GROUP_BY_OPTIONS, true)
            ? (string) $filters['group_by']
            : self::GROUP_DAY;

        $statuses = isset($filters['statuses']) && is_array($filters['statuses']) && count($filters['statuses']) > 0
            ? array_values(array_filter(array_map('strval', $filters['statuses']), fn (string $s): bool => OrderStatus::tryFrom($s) !== null))
            : [OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value];

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'group_by' => $groupBy,
            'shop_id' => isset($filters['shop_id']) && $filters['shop_id'] !== '' ? (int) $filters['shop_id'] : null,
            'deliveryman_id' => isset($filters['deliveryman_id']) && $filters['deliveryman_id'] !== '' ? (int) $filters['deliveryman_id'] : null,
            'category_id' => isset($filters['category_id']) && $filters['category_id'] !== '' ? (int) $filters['category_id'] : null,
            'statuses' => count($statuses) > 0 ? $statuses : [OrderStatus::DELIVERED->value, OrderStatus::RECEIVED->value],
        ];
    }

    private function bucketKey(string $date, string $groupBy): string
    {
        $d = CarbonImmutable::parse($date);

        return match ($groupBy) {
            self::GROUP_DAY => $d->format('Y-m-d'),
            self::GROUP_WEEK => $d->format('o-\WW'),
            self::GROUP_MONTH => $d->format('Y-m'),
            default => $d->format('Y-m-d'),
        };
    }

    /**
     * @param  array<string, array{label: string, orders: int, gross: int, discount: int}>  $buckets
     * @return array<int, array{label: string, orders: int, gross: int, discount: int}>
     */
    private function fillEmptyPeriods(string $from, string $to, string $groupBy, array $buckets): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();

        if ($start->greaterThan($end)) {
            return array_values($buckets);
        }

        $cursor = match ($groupBy) {
            self::GROUP_WEEK => $start->startOfWeek(),
            self::GROUP_MONTH => $start->startOfMonth(),
            default => $start,
        };

        $step = match ($groupBy) {
            self::GROUP_WEEK => '+1 week',
            self::GROUP_MONTH => '+1 month',
            default => '+1 day',
        };

        $result = [];
        $guard = 0;

        while ($cursor->lessThanOrEqualTo($end) && $guard < 400) {
            $key = $this->bucketKey($cursor->format('Y-m-d'), $groupBy);
            $result[] = $buckets[$key] ?? ['label' => $key, 'orders' => 0, 'gross' => 0, 'discount' => 0];
            $cursor = $cursor->modify($step);
            $guard++;
        }

        return $result;
    }
}
