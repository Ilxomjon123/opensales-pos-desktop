<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer\Reports;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\Reports\SalesReportFilterRequest;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\CsvExporter;
use App\Services\Reports\SalesReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SalesReportController extends Controller
{
    public function __construct(
        private readonly SalesReportService $reportService,
        private readonly CsvExporter $exporter,
    ) {}

    public function index(SalesReportFilterRequest $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();
        $report = $this->reportService->generate($dealerId, $filters);

        return Inertia::render('Dealer/Reports/Sales', [
            'report' => $report,
            'filters' => [
                'date_from' => $report['meta']['date_from'],
                'date_to' => $report['meta']['date_to'],
                'group_by' => $report['meta']['group_by'],
                'shop_id' => $filters['shop_id'],
                'deliveryman_id' => $filters['deliveryman_id'],
                'category_id' => $filters['category_id'],
                'statuses' => $report['meta']['statuses'],
            ],
            'groupByOptions' => $this->groupByOptions(),
            'statusOptions' => $this->statusOptions(),
            'shops' => Inertia::defer(fn () => $this->shops($dealerId)),
            'deliverymen' => Inertia::defer(fn () => $this->deliverymen($dealerId)),
            'categories' => Inertia::defer(fn () => $this->categories($dealerId)),
        ]);
    }

    public function export(SalesReportFilterRequest $request): StreamedResponse|JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;
        $filters = $request->filters();

        $filename = sprintf(
            'sales-report-%s.csv',
            CarbonImmutable::now()->format('Y-m-d-His'),
        );

        return $this->exporter->stream(
            filename: $filename,
            headers: ['Kesim', 'Buyurtmalar', 'Miqdor', 'Brutto', 'Chegirma', 'Sof', 'O\'rtacha chek'],
            rowsProvider: fn (): iterable => $this->reportService->exportRows($dealerId, $filters),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function groupByOptions(): array
    {
        return [
            ['value' => SalesReportService::GROUP_DAY, 'label' => 'Kunlik'],
            ['value' => SalesReportService::GROUP_WEEK, 'label' => 'Haftalik'],
            ['value' => SalesReportService::GROUP_MONTH, 'label' => 'Oylik'],
            ['value' => SalesReportService::GROUP_SHOP, 'label' => 'Mijoz'],
            ['value' => SalesReportService::GROUP_DELIVERYMAN, 'label' => 'Yetkazib beruvchi'],
            ['value' => SalesReportService::GROUP_CATEGORY, 'label' => 'Kategoriya'],
            ['value' => SalesReportService::GROUP_PRODUCT, 'label' => 'Mahsulot'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            static fn (OrderStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
            OrderStatus::cases(),
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function shops(int $dealerId): array
    {
        return Shop::query()
            ->forDealer($dealerId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Shop $s): array => ['id' => $s->id, 'name' => (string) $s->name])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function deliverymen(int $dealerId): array
    {
        return User::query()
            ->where('dealer_id', $dealerId)
            ->where('role', UserRole::DELIVERYMAN)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $u): array => ['id' => $u->id, 'name' => (string) $u->name])
            ->all();
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
}
