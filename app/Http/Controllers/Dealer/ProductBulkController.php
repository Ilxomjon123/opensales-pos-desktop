<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\BulkAdjustPriceRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\BulkPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductBulkController extends Controller
{
    public function __construct(private readonly BulkPriceService $bulk) {}

    public function edit(Request $request): Response
    {
        $this->authorize('updateAny', Product::class);

        $dealerId = (int) $request->user()->dealer_id;

        return Inertia::render('Dealer/Products/BulkPrice', [
            'categories' => ProductCategory::query()
                ->forDealer($dealerId)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ]);
    }

    public function adjust(BulkAdjustPriceRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('updateAny', Product::class);

        $dealerId = (int) $request->user()->dealer_id;
        $data = $request->validated();

        $result = $this->bulk->adjust(
            dealerId: $dealerId,
            params: [
                'scope' => $data['scope'],
                'category_id' => $data['category_id'] ?? null,
                'mode' => $data['mode'],
                'value' => (int) $data['value'],
                'direction' => $data['direction'],
            ],
            dryRun: (bool) ($data['dry_run'] ?? false),
        );

        if ((bool) ($data['dry_run'] ?? false)) {
            return response()->json($result);
        }

        return back()->with('status', "Narx yangilandi: {$result['updated']} ta mahsulot");
    }

    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('updateAny', Product::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $dealerId = (int) $request->user()->dealer_id;
        $result = $this->bulk->importCsv($dealerId, $request->file('file'));

        $msg = "CSV: {$result['updated']} ta yangilandi";
        if (count($result['skipped']) > 0) {
            $msg .= ', '.count($result['skipped'])." ta o'tkazib yuborildi";
        }

        return back()->with('status', $msg)->with('bulk_import_skipped', $result['skipped']);
    }
}
