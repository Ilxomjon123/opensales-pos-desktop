<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Reports;

use App\Enums\ReturnDisposition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\Reports\ReturnsReportFilterRequest;
use App\Services\CsvExporter;
use App\Services\Reports\ReturnsReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReturnsReportController extends Controller
{
    public function __construct(
        private readonly ReturnsReportService $reportService,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(ReturnsReportFilterRequest $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();
        $report = $this->reportService->generate($dealerId, $filters);

        return Inertia::render('Dealer/Reports/Returns', [
            'report' => $report,
            'filters' => [
                'date_from' => $report['meta']['date_from'],
                'date_to' => $report['meta']['date_to'],
                'source' => $filters['source'],
                'disposition' => $filters['disposition'],
            ],
            'sourceOptions' => $this->sourceOptions(),
            'dispositionOptions' => $this->dispositionOptions(),
        ]);
    }

    public function export(ReturnsReportFilterRequest $request): StreamedResponse|JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();

        $filename = sprintf(
            'returns-report-%s.csv',
            CarbonImmutable::now()->format('Y-m-d-His'),
        );

        return $this->exporter->stream(
            filename: $filename,
            headers: ['Kalit', 'Qiymat 1', 'Qiymat 2', 'Qiymat 3'],
            rowsProvider: fn (): iterable => $this->reportService->exportRows($dealerId, $filters),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sourceOptions(): array
    {
        return [
            ['value' => ReturnsReportService::SOURCE_SHOP, 'label' => 'Mijozdan'],
            ['value' => ReturnsReportService::SOURCE_SUPPLIER, 'label' => 'Ta\'minotchiga'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function dispositionOptions(): array
    {
        return array_map(
            static fn (ReturnDisposition $d): array => ['value' => $d->value, 'label' => $d->label()],
            ReturnDisposition::cases(),
        );
    }
}
