<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\Reports\DailyClosingFilterRequest;
use App\Services\CsvExporter;
use App\Services\Reports\DailyClosingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DailyClosingController extends Controller
{
    public function __construct(
        private readonly DailyClosingService $reportService,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(DailyClosingFilterRequest $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();
        $report = $this->reportService->generate($dealerId, $filters);

        return Inertia::render('Dealer/Reports/DailyClosing', [
            'report' => $report,
            'filters' => $report['meta'],
        ]);
    }

    public function export(DailyClosingFilterRequest $request): StreamedResponse|JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();

        $filename = sprintf(
            'daily-closing-%s.csv',
            CarbonImmutable::now()->format('Y-m-d-His'),
        );

        return $this->exporter->stream(
            filename: $filename,
            headers: ['Bo\'lim / kalit', 'Qiymat', 'Birlik / izoh'],
            rowsProvider: fn (): iterable => $this->reportService->exportRows($dealerId, $filters),
        );
    }
}
