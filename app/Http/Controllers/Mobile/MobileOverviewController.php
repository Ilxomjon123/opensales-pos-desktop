<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\BotVisibility;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Repositories\FinanceRepository;
use App\Services\DeliveryZoneService;
use App\Services\GeoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobil bosh ekran: mijoz a'zo bo'lgan barcha dillerlar (Telegram'dagi botlar
 * ro'yxati kabi), har birida shu mijozning shop(lar)i. Discovery: joylashuvni
 * qoplaydigan ochiq dillerlar (o'z-ro'yxati uchun).
 */
final class MobileOverviewController extends Controller
{
    public function __construct(
        private readonly FinanceRepository $finance,
        private readonly DeliveryZoneService $deliveryZones,
        private readonly GeoResolver $geo,
    ) {}

    /**
     * Ochiq dillerga to'g'ridan-to'g'ri ulanish (invitesiz). Mijozning oxirgi
     * joyi (manzil/koordinata) bo'yicha shu dillerda yangi shop ochiladi.
     */
    public function joinDealer(Request $request, Dealer $dealer): JsonResponse
    {
        abort_if(! $dealer->is_active || $dealer->visibility !== BotVisibility::PUBLIC, 404);

        /** @var Customer $customer */
        $customer = $request->user();

        // Tayanch joy: home'da tanlangan joy (from_shop_id) yoki oxirgi joy.
        $fromShopId = $request->integer('from_shop_id');
        $base = null;
        if ($fromShopId > 0) {
            $base = ShopMember::query()
                ->forCustomer($customer->id)
                ->whereHas('shop', fn ($q) => $q->where('id', $fromShopId))
                ->with('shop')
                ->first()?->shop;
        }
        $base ??= ShopMember::query()
            ->forCustomer($customer->id)
            ->with('shop')
            ->latest('id')
            ->first()?->shop;

        if ($base === null) {
            return response()->json(['message' => 'Avval manzil qo\'shing'], 422);
        }

        // Shu joy (nom+manzil) allaqachon shu dillerga ulanganmi — o'shani qaytaramiz.
        $existing = ShopMember::query()
            ->forCustomer($customer->id)
            ->whereHas('shop', fn ($q) => $q
                ->where('dealer_id', $dealer->id)
                ->where('name', $base->name)
                ->when(
                    $base->address === null,
                    fn ($w) => $w->whereNull('address'),
                    fn ($w) => $w->where('address', $base->address),
                ))
            ->with('shop')
            ->first();
        if ($existing !== null) {
            return response()->json(['shop_id' => $existing->shop_id, 'dealer_id' => $dealer->id], 200);
        }

        $shop = Shop::query()->create([
            'dealer_id' => $dealer->id,
            'name' => $base->name,
            'phone' => $customer->phone,
            'address' => $base->address,
            'region' => $base->region,
            'district' => $base->district,
            'latitude' => $base->latitude,
            'longitude' => $base->longitude,
            'contact_person' => $customer->name ?? $customer->phone,
            'balance' => 0,
            'is_active' => true,
        ]);

        ShopMember::query()->create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'name' => $base->name,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return response()->json(['shop_id' => $shop->id, 'dealer_id' => $dealer->id], 201);
    }

    public function dealers(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $members = ShopMember::query()
            ->forCustomer($customer->id)
            ->active()
            ->with(['shop.dealer'])
            ->get()
            ->filter(fn (ShopMember $m) => $m->shop !== null
                && $m->shop->is_active
                && $m->shop->dealer !== null
                && $m->shop->dealer->is_active);

        $dealers = $members
            ->groupBy(fn (ShopMember $m) => $m->shop->dealer_id)
            ->map(function ($group) {
                /** @var ShopMember $first */
                $first = $group->first();
                $dealer = $first->shop->dealer;

                $currency = $dealer->currency ?? Currency::UZS;

                return [
                    'id' => $dealer->id,
                    'name' => $dealer->name,
                    'bot_username' => $dealer->bot_username,
                    'display_name' => $dealer->effectiveBotDisplayName(),
                    'contact_phone' => $dealer->contact_phone,
                    'min_order_amount' => (int) $dealer->min_order_amount,
                    'currency' => $currency->value,
                    'currency_symbol' => $currency->symbol(),
                    'shops' => $group->map(fn (ShopMember $m) => $this->shopRow($m->shop))->values(),
                ];
            })
            ->values();

        return response()->json(['dealers' => $dealers]);
    }

    /**
     * Joylashuvni qoplaydigan ochiq dillerlar — o'z-ro'yxatdan oldin ko'rsatish uchun.
     */
    public function discover(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        // Mijozning manzillari (hudud/tuman) — shu hududni qoplaydigan ochiq dillerlar.
        $shops = ShopMember::query()
            ->forCustomer($customer->id)
            ->where('is_active', true)
            ->with('shop:id,dealer_id,region,district')
            ->get()
            ->pluck('shop')
            ->filter();

        $areas = $shops
            ->map(fn (Shop $s): array => ['region' => $s->region, 'district' => $s->district])
            ->filter(fn (array $a) => $a['region'] !== null && $a['region'] !== '')
            ->unique(fn (array $a) => $a['region'].'|'.$a['district'])
            ->values();

        // Manzil yo'q bo'lsa — qoplashni aniqlab bo'lmaydi, ro'yxat bo'sh.
        if ($areas->isEmpty()) {
            return response()->json(['dealers' => []]);
        }

        $joinedDealerIds = $shops->pluck('dealer_id')->unique()->all();

        $dealers = Dealer::query()
            ->where('is_active', true)
            ->where('visibility', BotVisibility::PUBLIC)
            ->whereNotIn('id', $joinedDealerIds === [] ? [0] : $joinedDealerIds)
            ->get()
            // Diller mijozning kamida bitta manzilini yetkazib berish hududida qoplasa.
            ->filter(fn (Dealer $d) => $areas->contains(
                fn (array $a) => $this->deliveryZones->covers($d, $a['region'], $a['district'])
            ))
            ->map(fn (Dealer $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'display_name' => $d->effectiveBotDisplayName(),
                'contact_phone' => $d->contact_phone,
            ])
            ->values();

        return response()->json(['dealers' => $dealers]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shopRow(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'address' => $shop->address,
            'balance' => (int) $shop->balance,
            'pending_total' => $this->finance->shopPendingTotal($shop->id),
        ];
    }
}
