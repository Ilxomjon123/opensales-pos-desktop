<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Shop;
use App\Support\Translit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShopBalanceController extends Controller
{
    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $date = $this->resolveDate($request->string('date')->toString());
        $cutoff = $date->endOfDay();

        $allowedSorts = ['name', 'balance'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $search = trim($request->string('search')->toString());

        $balanceSub = Payment::query()
            ->selectRaw("shop_id, SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END) AS historical_balance")
            ->where('dealer_id', $dealerId)
            ->where('created_at', '<=', $cutoff)
            ->groupBy('shop_id');

        $shops = Shop::query()
            ->forDealer($dealerId)
            ->select('shops.id', 'shops.name', 'shops.phone', 'shops.region', 'shops.is_active', 'shops.parent_shop_id')
            ->selectRaw('COALESCE(p.historical_balance, 0) AS balance')
            ->leftJoinSub($balanceSub, 'p', 'p.shop_id', '=', 'shops.id')
            ->when($search !== '', fn ($q) => Translit::applyLike($q, ['shops.name', 'shops.phone'], $search))
            ->orderBy($sort, $direction)
            ->orderBy('shops.id')
            ->paginate(50)
            ->withQueryString();

        $shopIds = collect($shops->items())->pluck('id')->all();

        // Bosh filiallar uchun: barcha filiallari saldosini cutoffgacha yig'amiz.
        $branchBalances = [];

        if ($shopIds !== []) {
            $rows = Shop::query()
                ->forDealer($dealerId)
                ->whereIn('parent_shop_id', $shopIds)
                ->leftJoinSub($balanceSub, 'p', 'p.shop_id', '=', 'shops.id')
                ->selectRaw('shops.parent_shop_id AS pid, COALESCE(SUM(p.historical_balance), 0) AS sum_balance')
                ->groupBy('shops.parent_shop_id')
                ->get();

            foreach ($rows as $row) {
                $branchBalances[(int) $row->pid] = (int) $row->sum_balance;
            }
        }

        $parentNames = [];
        $parentIds = collect($shops->items())->pluck('parent_shop_id')->filter()->unique()->all();

        if ($parentIds !== []) {
            $parentNames = Shop::query()
                ->whereIn('id', $parentIds)
                ->pluck('name', 'id')
                ->all();
        }

        $rows = collect($shops->items())->map(function ($shop) use ($branchBalances, $parentNames) {
            $own = (int) $shop->balance;
            $branchSum = $branchBalances[(int) $shop->id] ?? 0;
            $isMain = $shop->parent_shop_id === null;

            return [
                'id' => (int) $shop->id,
                'name' => $shop->name,
                'phone' => $shop->phone,
                'region' => $shop->region,
                'is_active' => (bool) $shop->is_active,
                'balance' => $own,
                'parent_shop_id' => $shop->parent_shop_id !== null ? (int) $shop->parent_shop_id : null,
                'parent_name' => $shop->parent_shop_id !== null
                    ? ($parentNames[(int) $shop->parent_shop_id] ?? null)
                    : null,
                'is_main_branch' => $isMain,
                'branches_balance_sum' => $isMain ? $branchSum : 0,
                'total_balance_with_branches' => $isMain ? $own + $branchSum : $own,
            ];
        })->all();

        $totals = Payment::query()
            ->where('dealer_id', $dealerId)
            ->where('created_at', '<=', $cutoff)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) AS net,
                COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) AS credits,
                COALESCE(SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END), 0) AS debits
            ")
            ->first();

        return Inertia::render('Dealer/ShopsBalance/Index', [
            'shops' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $shops->currentPage(),
                    'last_page' => $shops->lastPage(),
                    'per_page' => $shops->perPage(),
                    'total' => $shops->total(),
                    'from' => $shops->firstItem(),
                    'to' => $shops->lastItem(),
                ],
                'links' => [
                    'first' => $shops->url(1),
                    'last' => $shops->url($shops->lastPage()),
                    'prev' => $shops->previousPageUrl(),
                    'next' => $shops->nextPageUrl(),
                ],
            ],
            'totals' => [
                'net' => (int) ($totals->net ?? 0),
                'credits' => (int) ($totals->credits ?? 0),
                'debits' => (int) ($totals->debits ?? 0),
            ],
            'filters' => [
                'date' => $date->toDateString(),
                'search' => $search !== '' ? $search : null,
            ],
            'sort' => ['column' => $sort, 'direction' => $direction],
        ]);
    }

    private function resolveDate(string $raw): CarbonImmutable
    {
        if ($raw === '') {
            return CarbonImmutable::today();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }
}
