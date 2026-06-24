<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\DealerActivityReportFilterRequest;
use App\Services\CsvExporter;
use App\Services\Reports\DealerActivityReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DealerActivityReportController extends Controller
{
    public function __construct(
        private readonly DealerActivityReportService $reportService,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(DealerActivityReportFilterRequest $request): Response
    {
        $filters = $request->filters();
        $report = $this->reportService->generate($filters);

        return Inertia::render('Admin/Reports/DealerActivity', [
            'report' => $report,
            'filters' => [
                'date_from' => $report['meta']['date_from'],
                'date_to' => $report['meta']['date_to'],
                'status' => $filters['status'],
            ],
            'statusOptions' => [
                ['value' => DealerActivityReportService::STATUS_ACTIVE, 'label' => 'Faol'],
                ['value' => DealerActivityReportService::STATUS_AT_RISK, 'label' => 'Xavf ostida'],
                ['value' => DealerActivityReportService::STATUS_INACTIVE, 'label' => 'Faol emas'],
            ],
        ]);
    }

    public function export(DealerActivityReportFilterRequest $request): StreamedResponse|JsonResponse
    {
        $filters = $request->filters();
        $filename = sprintf('dealer-activity-%s.csv', CarbonImmutable::now()->format('Y-m-d-His'));

        return $this->exporter->stream(
            filename: $filename,
            headers: ['Diller', 'Faol', 'Do\'konlar', 'MAU', 'Buyurtmalar', 'Aylanma', 'Chastota/oy', 'Oxirgi zakas', 'Kun', 'Status'],
            rowsProvider: fn (): iterable => $this->reportService->exportRows($filters),
        );
    }
}
