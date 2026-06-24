<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\Reports\ProfitReportFilterRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CsvExporter;
use App\Services\Reports\ProfitReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProfitReportController extends Controller
{
    public function __construct(
        private readonly ProfitReportService $reportService,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(ProfitReportFilterRequest $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();
        $report = $this->reportService->generate($dealerId, $filters);

        return Inertia::render('Dealer/Reports/Profit', [
            'report' => $report,
            'filters' => [
                'date_from' => $report['meta']['date_from'],
                'date_to' => $report['meta']['date_to'],
                'category_id' => $filters['category_id'],
                'product_id' => $filters['product_id'],
            ],
            'categories' => Inertia::defer(fn () => $this->categories($dealerId)),
            'products' => Inertia::defer(fn () => $this->products($dealerId)),
        ]);
    }

    public function export(ProfitReportFilterRequest $request): StreamedResponse|JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();

        $filename = sprintf(
            'profit-report-%s.csv',
            CarbonImmutable::now()->format('Y-m-d-His'),
        );

        return $this->exporter->stream(
            filename: $filename,
            headers: [
                'Mahsulot', 'Kategoriya', 'Sotilgan miqdor',
                'O\'rt. sotuv', 'O\'rt. tannarx',
                'Daromad', 'Tannarx (COGS)', 'Sof foyda',
                'Marja %', 'Tannarx kiritilgan',
            ],
            rowsProvider: fn (): iterable => $this->reportService->exportRows($dealerId, $filters),
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function categories(int $dealerId): array
    {
        return ProductCategory::query()
            ->where('dealer_id', $dealerId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (ProductCategory $c): array => ['id' => $c->id, 'name' => (string) $c->name])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function products(int $dealerId): array
    {
        return Product::query()
            ->forDealer($dealerId)
            ->orderBy('name')
            ->limit(2000)
            ->get(['id', 'name'])
            ->map(static fn (Product $p): array => ['id' => $p->id, 'name' => (string) $p->name])
            ->all();
    }
}
