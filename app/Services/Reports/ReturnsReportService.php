<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReturnDisposition;
use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Qaytarishlar hisoboti — mijozdan + ta'minotchiga qaytarishlar bo'yicha tahlil.
 *
 * Manba: `transactions` (type IN shop_return / supplier_return) +
 * `transaction_details` (qty × unit_cost qiymati, disposition).
 *
 * Hisoblanadi:
 *  - umumiy operatsiya soni, miqdor va qiymat (qty × unit_cost)
 *  - manba kesimi (shop_return / supplier_return)
 *  - disposition kesimi (restock — stokka qaytdi / spoilage — yo'qotish)
 *  - top mahsulotlar (eng ko'p qaytarilgan, eng ko'p yo'qotish bergan)
 *  - top mijozlar (eng ko'p qaytaruvchilar)
 *
 * @phpstan-type ReturnsFilters array{
 *     date_from?: string, date_to?: string,
 *     source?: string|null, disposition?: string|null,
 * }
 */
final class ReturnsReportService
{
    public const SOURCE_SHOP = 'shop_return';

    public const SOURCE_SUPPLIER = 'supplier_return';

    public const SOURCE_OPTIONS = [self::SOURCE_SHOP, self::SOURCE_SUPPLIER];

    private const TOP_LIMIT = 20;

    /**
     * @param  ReturnsFilters  $filters
     * @return array{
     *     summary: array<string,int|float>,
     *     top_products: list<array<string,mixed>>,
     *     top_shops: list<array<string,mixed>>,
     *     by_disposition: list<array<string,mixed>>,
     *     meta: array<string,mixed>,
     * }
     */
    public function generate(int $dealerId, array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        return [
            'summary' => $this->summary($dealerId, $normalized),
            'top_products' => $this->topProducts($dealerId, $normalized),
            'top_shops' => $this->topShops($dealerId, $normalized),
            'by_disposition' => $this->byDisposition($dealerId, $normalized),
            'meta' => $normalized,
        ];
    }

    /**
     * @param  ReturnsFilters  $filters
     * @return iterable<int, list<string|int|float|null>>
     */
    public function exportRows(int $dealerId, array $filters): iterable
    {
        $report = $this->generate($dealerId, $filters);

        yield ['# UMUMIY', '', '', ''];
        yield ['Operatsiyalar soni', $report['summary']['ops_count'], '', ''];
        yield ['Jami miqdor', $report['summary']['total_qty'], '', ''];
        yield ['Jami qiymat (so\'m)', $report['summary']['total_value'], '', ''];
        yield ['Mijozdan qaytarish', $report['summary']['shop_value'], 'so\'m', ''];
        yield ['Ta\'minotchiga qaytarish', $report['summary']['supplier_value'], 'so\'m', ''];
        yield ['Stokka qaytarildi', $report['summary']['restock_value'], 'so\'m', ''];
        yield ['Yo\'qotish (spoilage)', $report['summary']['spoilage_value'], 'so\'m', ''];
        yield ['', '', '', ''];

        yield ['# TOP MAHSULOTLAR', 'Miqdor', 'Qiymat', 'Operatsiya'];
        foreach ($report['top_products'] as $row) {
            yield [$row['name'], $row['qty'], $row['value'], $row['ops']];
        }
        yield ['', '', '', ''];

        yield ['# TOP MIJOZLAR (vozvrat soni)', 'Operatsiya', 'Miqdor', 'Qiymat'];
        foreach ($report['top_shops'] as $row) {
            yield [$row['name'], $row['ops'], $row['qty'], $row['value']];
        }
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string,
     *     source: ?string, disposition: ?string,
     * }  $filters
     * @return array<string, int|float>
     */
    private function summary(int $dealerId, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        $agg = DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->where('t.dealer_id', $dealerId)
            ->whereIn('t.type', [TransactionType::SHOP_RETURN->value, TransactionType::SUPPLIER_RETURN->value])
            ->whereBetween('t.created_at', [$from, $to])
            ->when($filters['source'] !== null, fn ($q) => $q->where('t.type', $filters['source']))
            ->when($filters['disposition'] !== null, fn ($q) => $q->where('td.disposition', $filters['disposition']))
            ->selectRaw('
                COUNT(DISTINCT t.id) as ops_count,
                COALESCE(SUM(td.qty), 0) as total_qty,
                COALESCE(SUM(td.qty * COALESCE(td.unit_cost, 0)), 0) as total_value,
                COALESCE(SUM(CASE WHEN t.type = ? THEN td.qty * COALESCE(td.unit_cost, 0) ELSE 0 END), 0) as shop_value,
                COALESCE(SUM(CASE WHEN t.type = ? THEN td.qty * COALESCE(td.unit_cost, 0) ELSE 0 END), 0) as supplier_value,
                COALESCE(SUM(CASE WHEN td.disposition = ? THEN td.qty * COALESCE(td.unit_cost, 0) ELSE 0 END), 0) as restock_value,
                COALESCE(SUM(CASE WHEN td.disposition = ? THEN td.qty * COALESCE(td.unit_cost, 0) ELSE 0 END), 0) as spoilage_value
            ', [
                TransactionType::SHOP_RETURN->value,
                TransactionType::SUPPLIER_RETURN->value,
                ReturnDisposition::RESTOCK->value,
                ReturnDisposition::SPOILAGE->value,
            ])
            ->first();

        return [
            'ops_count' => (int) ($agg->ops_count ?? 0),
            'total_qty' => (float) ($agg->total_qty ?? 0),
            'total_value' => (int) round((float) ($agg->total_value ?? 0)),
            'shop_value' => (int) round((float) ($agg->shop_value ?? 0)),
            'supplier_value' => (int) round((float) ($agg->supplier_value ?? 0)),
            'restock_value' => (int) round((float) ($agg->restock_value ?? 0)),
            'spoilage_value' => (int) round((float) ($agg->spoilage_value ?? 0)),
        ];
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string,
     *     source: ?string, disposition: ?string,
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function topProducts(int $dealerId, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        return DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->where('t.dealer_id', $dealerId)
            ->whereIn('t.type', [TransactionType::SHOP_RETURN->value, TransactionType::SUPPLIER_RETURN->value])
            ->whereBetween('t.created_at', [$from, $to])
            ->when($filters['source'] !== null, fn ($q) => $q->where('t.type', $filters['source']))
            ->when($filters['disposition'] !== null, fn ($q) => $q->where('td.disposition', $filters['disposition']))
            ->select([
                'td.product_id',
                DB::raw('MAX(td.product_name) as name'),
                DB::raw('COALESCE(SUM(td.qty), 0) as qty'),
                DB::raw('COALESCE(SUM(td.qty * COALESCE(td.unit_cost, 0)), 0) as value'),
                DB::raw('COUNT(DISTINCT t.id) as ops'),
            ])
            ->groupBy('td.product_id')
            ->orderByDesc('value')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($r): array => [
                'product_id' => (int) $r->product_id,
                'name' => (string) $r->name,
                'qty' => (float) $r->qty,
                'value' => (int) round((float) $r->value),
                'ops' => (int) $r->ops,
            ])
            ->all();
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string,
     *     source: ?string, disposition: ?string,
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function topShops(int $dealerId, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        return DB::table('transactions as t')
            ->join('transaction_details as td', 'td.transaction_id', '=', 't.id')
            ->join('shops', 'shops.id', '=', 't.shop_id')
            ->where('t.dealer_id', $dealerId)
            ->where('t.type', TransactionType::SHOP_RETURN->value)
            ->whereBetween('t.created_at', [$from, $to])
            ->when($filters['disposition'] !== null, fn ($q) => $q->where('td.disposition', $filters['disposition']))
            ->select([
                't.shop_id',
                DB::raw('MAX(shops.name) as name'),
                DB::raw('COUNT(DISTINCT t.id) as ops'),
                DB::raw('COALESCE(SUM(td.qty), 0) as qty'),
                DB::raw('COALESCE(SUM(td.qty * COALESCE(td.unit_cost, 0)), 0) as value'),
            ])
            ->groupBy('t.shop_id')
            ->orderByDesc('value')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($r): array => [
                'shop_id' => (int) $r->shop_id,
                'name' => (string) $r->name,
                'ops' => (int) $r->ops,
                'qty' => (float) $r->qty,
                'value' => (int) round((float) $r->value),
            ])
            ->all();
    }

    /**
     * @param  array{
     *     date_from: string, date_to: string,
     *     source: ?string, disposition: ?string,
     * }  $filters
     * @return list<array<string,mixed>>
     */
    private function byDisposition(int $dealerId, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        $rows = DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->where('t.dealer_id', $dealerId)
            ->whereIn('t.type', [TransactionType::SHOP_RETURN->value, TransactionType::SUPPLIER_RETURN->value])
            ->whereBetween('t.created_at', [$from, $to])
            ->when($filters['source'] !== null, fn ($q) => $q->where('t.type', $filters['source']))
            ->select([
                'td.disposition',
                DB::raw('COALESCE(SUM(td.qty), 0) as qty'),
                DB::raw('COALESCE(SUM(td.qty * COALESCE(td.unit_cost, 0)), 0) as value'),
                DB::raw('COUNT(*) as lines'),
            ])
            ->groupBy('td.disposition')
            ->get();

        $result = [];
        foreach (ReturnDisposition::cases() as $case) {
            $row = $rows->firstWhere('disposition', $case->value);
            $result[] = [
                'disposition' => $case->value,
                'label' => $case->label(),
                'lines' => $row !== null ? (int) $row->lines : 0,
                'qty' => $row !== null ? (float) $row->qty : 0.0,
                'value' => $row !== null ? (int) round((float) $row->value) : 0,
            ];
        }

        $nullRow = $rows->firstWhere('disposition', null);
        if ($nullRow !== null && (int) $nullRow->lines > 0) {
            $result[] = [
                'disposition' => null,
                'label' => 'Belgilanmagan',
                'lines' => (int) $nullRow->lines,
                'qty' => (float) $nullRow->qty,
                'value' => (int) round((float) $nullRow->value),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{
     *     date_from: string, date_to: string,
     *     source: ?string, disposition: ?string,
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

        $source = isset($filters['source']) && in_array($filters['source'], self::SOURCE_OPTIONS, true)
            ? (string) $filters['source']
            : null;

        $disposition = isset($filters['disposition']) && ReturnDisposition::tryFrom((string) $filters['disposition']) !== null
            ? (string) $filters['disposition']
            : null;

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'source' => $source,
            'disposition' => $disposition,
        ];
    }
}
