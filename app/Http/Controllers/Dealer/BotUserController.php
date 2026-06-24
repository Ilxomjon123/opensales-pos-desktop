<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Support\Translit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class BotUserController extends Controller
{
    private const ALLOWED_SORTS = ['name', 'joined_at', 'last_seen_at', 'orders_count'];

    private const ALLOWED_ACTIVITY = ['today', '7d', '30d', 'never', 'inactive'];

    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'last_seen_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $search = trim($request->string('search')->toString());
        $shopId = $request->integer('shop_id') ?: null;
        $status = $request->string('status')->toString();
        $activity = in_array($request->string('activity')->toString(), self::ALLOWED_ACTIVITY, true)
            ? $request->string('activity')->toString()
            : null;

        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $weekAgo = $now->copy()->subDays(7);
        $monthAgo = $now->copy()->subDays(30);

        $base = ShopMember::query()
            ->whereHas('shop', fn ($q) => $q->forDealer($dealerId))
            ->withCount('orders')
            ->withMax('orders as last_order_at', 'created_at')
            ->with(['shop:id,name,is_active,parent_shop_id']);

        if ($search !== '') {
            Translit::applyLike($base, ['name', 'username', 'telegram_id'], $search);
        }

        if ($shopId !== null) {
            $base->where('shop_id', $shopId);
        }

        if ($status === 'active') {
            $base->where('is_active', true);
        } elseif ($status === 'inactive') {
            $base->where('is_active', false);
        } elseif ($status === 'blocked') {
            $base->whereNotNull('blocked_at');
        }

        match ($activity) {
            'today' => $base->where('last_seen_at', '>=', $todayStart),
            '7d' => $base->where('last_seen_at', '>=', $weekAgo),
            '30d' => $base->where('last_seen_at', '>=', $monthAgo),
            'inactive' => $base->where(function ($q) use ($monthAgo): void {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $monthAgo);
            }),
            'never' => $base->doesntHave('orders'),
            default => null,
        };

        $base->orderBy($sort, $direction)->orderBy('id', 'desc');

        $members = $base->paginate(50)->withQueryString();

        $rows = collect($members->items())->map(fn (ShopMember $m) => [
            'id' => (int) $m->id,
            'telegram_id' => (int) $m->telegram_id,
            'name' => $m->name,
            'username' => $m->username,
            'is_active' => (bool) $m->is_active,
            'blocked_at' => $m->blocked_at?->toIso8601String(),
            'joined_at' => $m->joined_at?->toIso8601String(),
            'last_seen_at' => $m->last_seen_at?->toIso8601String(),
            'orders_count' => (int) $m->orders_count,
            'last_order_at' => $m->last_order_at !== null
                ? Carbon::parse($m->last_order_at)->toIso8601String()
                : null,
            'shop' => $m->shop !== null ? [
                'id' => (int) $m->shop->id,
                'name' => $m->shop->name,
                'is_active' => (bool) $m->shop->is_active,
            ] : null,
        ])->all();

        $kpi = $this->computeKpi($dealerId, $todayStart, $weekAgo, $monthAgo);

        $shops = Shop::query()
            ->forDealer($dealerId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])
            ->all();

        return Inertia::render('Dealer/BotUsers/Index', [
            'members' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                    'per_page' => $members->perPage(),
                    'total' => $members->total(),
                    'from' => $members->firstItem(),
                    'to' => $members->lastItem(),
                ],
                'links' => [
                    'first' => $members->url(1),
                    'last' => $members->url($members->lastPage()),
                    'prev' => $members->previousPageUrl(),
                    'next' => $members->nextPageUrl(),
                ],
            ],
            'kpi' => $kpi,
            'shops' => $shops,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'shop_id' => $shopId,
                'status' => in_array($status, ['active', 'inactive', 'blocked'], true) ? $status : null,
                'activity' => $activity,
            ],
            'sort' => ['column' => $sort, 'direction' => $direction],
        ]);
    }

    public function show(Request $request, ShopMember $member): JsonResponse
    {
        $dealerId = (int) $request->user()->dealer_id;

        $member->load(['shop:id,name,is_active,phone,address,region,district,balance,dealer_id']);

        abort_if($member->shop === null || (int) $member->shop->dealer_id !== $dealerId, 404);

        $recentOrders = Order::query()
            ->where('member_id', $member->id)
            ->latest('created_at')
            ->limit(10)
            ->get(['id', 'total', 'status', 'created_at'])
            ->map(fn (Order $o) => [
                'id' => (int) $o->id,
                'total' => (int) $o->total,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'created_at' => $o->created_at?->toIso8601String(),
            ])
            ->all();

        return response()->json([
            'id' => (int) $member->id,
            'telegram_id' => (int) $member->telegram_id,
            'name' => $member->name,
            'username' => $member->username,
            'is_active' => (bool) $member->is_active,
            'blocked_at' => $member->blocked_at?->toIso8601String(),
            'joined_at' => $member->joined_at?->toIso8601String(),
            'last_seen_at' => $member->last_seen_at?->toIso8601String(),
            'orders_count' => Order::query()->where('member_id', $member->id)->count(),
            'shop' => [
                'id' => (int) $member->shop->id,
                'name' => $member->shop->name,
                'is_active' => (bool) $member->shop->is_active,
                'phone' => $member->shop->phone,
                'address' => $member->shop->address,
                'region' => $member->shop->region,
                'district' => $member->shop->district,
                'balance' => (int) $member->shop->balance,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * @return array{total:int,active:int,inactive:int,blocked:int,today:int,week:int,month:int,never_ordered:int}
     */
    private function computeKpi(int $dealerId, Carbon $todayStart, Carbon $weekAgo, Carbon $monthAgo): array
    {
        $base = ShopMember::query()
            ->whereHas('shop', fn ($q) => $q->forDealer($dealerId));

        $total = (clone $base)->count();
        $active = (clone $base)->where('is_active', true)->count();
        $blocked = (clone $base)->whereNotNull('blocked_at')->count();
        $today = (clone $base)->where('last_seen_at', '>=', $todayStart)->count();
        $week = (clone $base)->where('last_seen_at', '>=', $weekAgo)->count();
        $month = (clone $base)->where('last_seen_at', '>=', $monthAgo)->count();
        $neverOrdered = (clone $base)->doesntHave('orders')->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'blocked' => $blocked,
            'today' => $today,
            'week' => $week,
            'month' => $month,
            'never_ordered' => $neverOrdered,
        ];
    }
}
