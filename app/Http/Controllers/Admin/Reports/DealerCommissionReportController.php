<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\DealerCommissionReportFilterRequest;
use App\Services\CsvExporter;
use App\Services\Reports\DealerCommissionReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DealerCommissionReportController extends Controller
{
    public function __construct(
        private readonly DealerCommissionReportService $reportService,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(DealerCommissionReportFilterRequest $request): Response
    {
        $report = $this->reportService->generate($request->filters());

        return Inertia::render('Admin/Reports/Commission', [
            'report' => $report,
            'filters' => $report['meta'],
        ]);
    }

    public function export(DealerCommissionReportFilterRequest $request): StreamedResponse|JsonResponse
    {
        $filters = $request->filters();
        $filename = sprintf('commission-%s.csv', CarbonImmutable::now()->format('Y-m-d-His'));

        return $this->exporter->stream(
            filename: $filename,
            headers: ['Diller', 'Buyurtmalar', 'Aylanma', 'Stavka %', 'Komissiya', 'To\'langan', 'Balans'],
            rowsProvider: fn (): iterable => $this->reportService->exportRows($filters),
        );
    }
}
