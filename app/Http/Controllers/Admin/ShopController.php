<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Shop;
use App\Support\Translit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class ShopController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $dealerFilter = $request->integer('dealer_id') ?: null;

        $shops = Shop::query()
            ->with('dealer:id,name,is_active')
            ->when($search !== '', fn ($q) => Translit::applyLike(
                $q, ['name', 'legal_name', 'phone', 'inn'], $search
            ))
            ->when($dealerFilter !== null, fn ($q) => $q->where('dealer_id', $dealerFilter))
            ->orderBy('id')
            ->get();

        $groups = $this->groupShopsByInn($shops);
        $paginator = $this->paginate($groups, $request);

        return Inertia::render('Admin/Shops/Index', [
            'groups' => $paginator,
            'totals' => $this->totals(),
            'dealerBalances' => $this->dealerBalances(),
            'dealers' => Dealer::query()->orderBy('name')->get(['id', 'name'])->values(),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'dealer_id' => $dealerFilter,
            ],
        ]);
    }

    /**
     * Mijozlarni INN bo'yicha guruhlash. INN bo'sh bo'lsa — alohida guruh (shop_<id>).
     */
    private function groupShopsByInn(Collection $shops): Collection
    {
        return $shops
            ->groupBy(fn (Shop $s) => $s->inn !== null && $s->inn !== '' ? 'inn_'.$s->inn : 'shop_'.$s->id)
            ->map(function (Collection $group, string $key): array {
                /** @var Shop $primary */
                $primary = $group->first();

                $dealers = $group->map(fn (Shop $s) => [
                    'shop_id' => $s->id,
                    'dealer_id' => $s->dealer_id,
                    'dealer_name' => $s->dealer?->name ?? '—',
                    'dealer_active' => (bool) ($s->dealer?->is_active ?? false),
                    'shop_name' => $s->name,
                    'phone' => $s->phone,
                    'contact_person' => $s->contact_person,
                    'address' => $s->address,
                    'balance' => (int) $s->balance,
                    'is_active' => (bool) $s->is_active,
                ])->values()->all();

                return [
                    'key' => $key,
                    'inn' => $primary->inn,
                    'name' => $primary->legal_name ?: $primary->name,
                    'phone' => $primary->phone,
                    'contact_person' => $primary->contact_person,
                    'region' => $primary->region,
                    'district' => $primary->district,
                    'address' => $primary->address,
                    'dealer_count' => count($dealers),
                    'total_balance' => (int) $group->sum('balance'),
                    'dealers' => $dealers,
                ];
            })
            ->sortByDesc(fn (array $g) => $g['dealer_count'])
            ->values();
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{
     *     current_page: int, last_page: int, per_page: int, total: int,
     *     from: int|null, to: int|null,
     * }}
     */
    private function paginate(Collection $groups, Request $request): array
    {
        $total = $groups->count();
        $perPage = self::PER_PAGE;
        $lastPage = (int) max(1, ceil($total / $perPage));
        $page = min(max(1, $request->integer('page', 1)), $lastPage);

        $items = $groups->forPage($page, $perPage)->values();

        return [
            'data' => $items->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
            ],
        ];
    }

    /**
     * @return array{
     *     shop_rows: int,
     *     unique_shops: int,
     *     shared_inn_groups: int,
     *     total_balance: int,
     *     total_debt: int,
     *     total_credit: int,
     * }
     */
    private function totals(): array
    {
        $shops = Shop::query()->get(['id', 'inn', 'balance']);

        $groups = $shops->groupBy(
            fn (Shop $s) => $s->inn !== null && $s->inn !== '' ? 'inn_'.$s->inn : 'shop_'.$s->id
        );

        $totalBalance = (int) $shops->sum('balance');
        $totalDebt = (int) $shops->where('balance', '<', 0)->sum('balance');
        $totalCredit = (int) $shops->where('balance', '>', 0)->sum('balance');

        return [
            'shop_rows' => $shops->count(),
            'unique_shops' => $groups->count(),
            'shared_inn_groups' => $groups->filter(fn (Collection $g) => $g->count() > 1)->count(),
            'total_balance' => $totalBalance,
            'total_debt' => $totalDebt,
            'total_credit' => $totalCredit,
        ];
    }

    /**
     * Har diller bo'yicha saldo holati.
     *
     * @return list<array{
     *     id: int, name: string, is_active: bool,
     *     shops_count: int, total_balance: int, debt: int, credit: int
     * }>
     */
    private function dealerBalances(): array
    {
        return Dealer::query()
            ->withCount('shops')
            ->withSum('shops as total_balance', 'balance')
            ->withSum(['shops as debt' => fn ($q) => $q->where('balance', '<', 0)], 'balance')
            ->withSum(['shops as credit' => fn ($q) => $q->where('balance', '>', 0)], 'balance')
            ->orderBy('name')
            ->get()
            ->map(fn (Dealer $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'is_active' => (bool) $d->is_active,
                'shops_count' => (int) $d->shops_count,
                'total_balance' => (int) ($d->total_balance ?? 0),
                'debt' => (int) ($d->debt ?? 0),
                'credit' => (int) ($d->credit ?? 0),
            ])
            ->values()
            ->all();
    }
}
