<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\PlatformSalesReportFilterRequest;
use App\Models\Dealer;
use App\Services\CsvExporter;
use App\Services\Reports\PlatformSalesReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PlatformSalesReportController extends Controller
{
    public function __construct(
        private readonly PlatformSalesReportService $reportService,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(PlatformSalesReportFilterRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reportService->generate($filters);

        return Inertia::render('Admin/Reports/Sales', [
            'report' => $report,
            'filters' => [
                'date_from' => $report['meta']['date_from'],
                'date_to' => $report['meta']['date_to'],
                'group_by' => $report['meta']['group_by'],
                'dealer_id' => $filters['dealer_id'],
            ],
            'groupByOptions' => [
                ['value' => PlatformSalesReportService::GROUP_DAY, 'label' => 'Kunlik'],
                ['value' => PlatformSalesReportService::GROUP_WEEK, 'label' => 'Haftalik'],
                ['value' => PlatformSalesReportService::GROUP_MONTH, 'label' => 'Oylik'],
                ['value' => PlatformSalesReportService::GROUP_DEALER, 'label' => 'Diller'],
            ],
            'dealers' => Inertia::defer(fn () => Dealer::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Dealer $d): array => ['id' => $d->id, 'name' => (string) $d->name])->all()),
        ]);
    }

    public function export(PlatformSalesReportFilterRequest $request): StreamedResponse|JsonResponse
    {
        $filters = $request->filters();
        $filename = sprintf('platform-sales-%s.csv', CarbonImmutable::now()->format('Y-m-d-His'));

        return $this->exporter->stream(
            filename: $filename,
            headers: ['Kesim', 'Buyurtmalar', 'Brutto', 'Chegirma', 'Sof', 'O\'rt. chek'],
            rowsProvider: fn (): iterable => $this->reportService->exportRows($filters),
        );
    }
}
