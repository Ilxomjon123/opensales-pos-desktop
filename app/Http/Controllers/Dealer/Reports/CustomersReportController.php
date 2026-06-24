<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\Reports\CustomersReportFilterRequest;
use App\Models\Shop;
use App\Services\CsvExporter;
use App\Services\Reports\CustomersReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CustomersReportController extends Controller
{
    public function __construct(
        private readonly CustomersReportService $reportService,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(CustomersReportFilterRequest $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();
        $report = $this->reportService->generate($dealerId, $filters);

        return Inertia::render('Dealer/Reports/Customers', [
            'report' => $report,
            'filters' => [
                'date_from' => $report['meta']['date_from'],
                'date_to' => $report['meta']['date_to'],
                'activity' => $filters['activity'],
                'region' => $filters['region'],
                'district' => $filters['district'],
            ],
            'activityOptions' => $this->activityOptions(),
            'regions' => Inertia::defer(fn () => $this->regions($dealerId)),
            'districts' => Inertia::defer(fn () => $this->districts($dealerId)),
        ]);
    }

    public function export(CustomersReportFilterRequest $request): StreamedResponse|JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();

        $filename = sprintf(
            'customers-report-%s.csv',
            CarbonImmutable::now()->format('Y-m-d-His'),
        );

        return $this->exporter->stream(
            filename: $filename,
            headers: [
                'Mijoz', 'Viloyat', 'Tuman',
                'Buyurtmalar', 'Brutto', 'Chegirma', 'Sof', 'O\'rt. chek',
                'Chastota (oyiga)', 'Oxirgi zakas', 'O\'tdi (kun)',
                'Balans', 'ABC', 'Faollik',
            ],
            rowsProvider: fn (): iterable => $this->reportService->exportRows($dealerId, $filters),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function activityOptions(): array
    {
        return [
            ['value' => CustomersReportService::ACTIVITY_ACTIVE, 'label' => 'Faol'],
            ['value' => CustomersReportService::ACTIVITY_AT_RISK, 'label' => 'Xavf ostida'],
            ['value' => CustomersReportService::ACTIVITY_INACTIVE, 'label' => 'Faol emas'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function regions(int $dealerId): array
    {
        return Shop::query()
            ->forDealer($dealerId)
            ->whereNotNull('region')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->map(static fn (string $r): array => ['value' => $r, 'label' => $r])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function districts(int $dealerId): array
    {
        return Shop::query()
            ->forDealer($dealerId)
            ->whereNotNull('district')
            ->distinct()
            ->orderBy('district')
            ->pluck('district')
            ->map(static fn (string $d): array => ['value' => $d, 'label' => $d])
            ->all();
    }
}
