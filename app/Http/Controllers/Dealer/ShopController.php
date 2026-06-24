<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Contracts\ForwardGeocoderInterface;
use App\Contracts\InnLookupServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreShopRequest;
use App\Http\Requests\Dealer\UpdateShopRequest;
use App\Http\Resources\DeliverymanResource;
use App\Http\Resources\ShopInviteResource;
use App\Http\Resources\ShopMemberResource;
use App\Http\Resources\ShopResource;
use App\Http\Resources\ShopVisitResource;
use App\Models\Country;
use App\Models\DirectoryShop;
use App\Models\Shop;
use App\Models\User;
use App\Services\Geo\GeoCatalog;
use App\Services\Geo\RegionMatcher;
use App\Services\GeoResolver;
use App\Services\ShopInviteService;
use App\Support\Translit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ShopController extends Controller
{
    public function __construct(
        private readonly ShopInviteService $inviteService,
        private readonly GeoCatalog $geoCatalog,
    ) {}

    /**
     * Joriy diller davlati (yo'q bo'lsa — O'zbekiston).
     */
    private function dealerCountry(Request $request): ?Country
    {
        return $request->user()?->dealer?->country
            ?? Country::query()->where('code', 'uz')->first();
    }

    /**
     * Diller davlatiga mos viloyat/tuman ro'yxati (select uchun).
     *
     * @return array<int, array{name: string, districts: array<int, string>}>
     */
    private function regionOptions(Request $request): array
    {
        $country = $this->dealerCountry($request);

        return $country !== null ? $this->geoCatalog->regionOptions($country) : [];
    }

    /**
     * Xarita default markazi — diller davlatiga mos (RU diller → Rossiya).
     *
     * @return array{lat: float, lng: float, zoom: int}|null
     */
    private function mapDefaults(Request $request): ?array
    {
        $country = $this->dealerCountry($request);

        if ($country === null || $country->default_latitude === null || $country->default_longitude === null) {
            return null;
        }

        return [
            'lat' => (float) $country->default_latitude,
            'lng' => (float) $country->default_longitude,
            'zoom' => (int) $country->default_zoom,
        ];
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Shop::class);

        $dealerId = (int) $request->user()->dealer_id;
        $hasZones = $request->user()->dealer?->deliveryZones()->exists() ?? false;

        // Faollik chegarasi (kun). Default 2 hafta; 1..365 oralig'ida cheklanadi.
        $inactiveDays = max(1, min(365, $request->integer('inactive_days', 14) ?: 14));

        // Faollik bo'yicha filtr: '', 'active', 'inactive'
        $activity = in_array($request->string('activity')->toString(), ['active', 'inactive'], true)
            ? $request->string('activity')->toString()
            : '';

        $shops = Shop::query()
            ->forDealer($dealerId)
            ->with(['deliveryman', 'parent'])
            ->withCount(['members', 'branches'])
            ->withCount(['members as active_members_count' => fn ($q) => $q->whereNull('blocked_at')])
            ->withMax('orders as last_order_at', 'created_at')
            ->withMax('visits as last_visit_at', 'visited_at')
            ->withCount('visits')
            ->withSum('pendingOrders as pending_orders_sum_total', 'total')
            ->withSum('branches as branches_sum_balance', 'balance')
            ->when($hasZones, fn ($q) => $q->withZoneCoverage())
            ->when($request->filled('search'), fn ($q) => Translit::applyLike(
                $q, ['name', 'phone'], (string) $request->string('search')
            ))
            ->when($request->filled('region'), fn ($q) => $q->where('region', $request->string('region')))
            ->when($request->filled('district'), fn ($q) => $q->where('district', $request->string('district')))
            ->when($hasZones && $request->boolean('outside_zone'), fn ($q) => $q->outsideDeliveryZone())
            ->when($activity === 'inactive', fn ($q) => $q->inactiveCustomer($inactiveDays))
            ->when($activity === 'active', fn ($q) => $q->activeCustomer($inactiveDays))
            // Faolsiz filtrda vakili yo'q mijozlar eng tepada
            ->when($activity === 'inactive', fn ($q) => $q->orderBy('members_count'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Dealer/Shops/Index', [
            'shops' => Inertia::scroll(fn () => ShopResource::collection($shops)),
            'filters' => array_merge(
                $request->only(['search', 'region', 'district', 'outside_zone']),
                ['activity' => $activity, 'inactive_days' => $inactiveDays],
            ),
            'regions' => $this->regionOptions($request),
            'hasDeliveryZones' => $hasZones,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Shop::class);

        $user = $request->user();
        $deliverymen = [];

        if ($user->isDealer()) {
            $deliverymen = User::query()->deliverymenFor((int) $user->dealer_id)->orderBy('name')->get();
        }

        return Inertia::render('Dealer/Shops/Create', [
            'deliverymen' => DeliverymanResource::collection($deliverymen),
            'regions' => $this->regionOptions($request),
            'mapDefaults' => $this->mapDefaults($request),
            'parentShops' => $this->parentShopOptions((int) $user->dealer_id),
        ]);
    }

    public function store(StoreShopRequest $request): RedirectResponse
    {
        $this->authorize('create', Shop::class);

        $user = $request->user();
        $data = $request->safe()->except(['photo', 'photo_source_path']);

        $data['dealer_id'] = $user->dealer_id;
        $data['parent_shop_id'] = $request->integer('parent_shop_id') ?: null;

        if ($user->isDeliveryman()) {
            $data['deliveryman_id'] = $user->id;
        } else {
            $data['deliveryman_id'] = $request->integer('deliveryman_id') ?: null;
        }

        $dealerDir = "dealers/{$user->dealer_id}/shops";

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store($dealerDir, 'public');
        } elseif ($sourcePath = $request->string('photo_source_path')->toString()) {
            $disk = Storage::disk('public');

            if ($disk->exists($sourcePath)) {
                $newPath = $dealerDir.'/'.Str::uuid()->toString().'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);
                $disk->copy($sourcePath, $newPath);
                $data['photo'] = $newPath;
            }
        }

        $shop = Shop::query()->create($data);

        return redirect()
            ->route('dealer.shops.show', $shop)
            ->with('status', 'Mijoz yaratildi. Endi mijoz vakilini biriktiring.');
    }

    public function show(Request $request, Shop $shop): Response
    {
        $this->authorize('view', $shop);

        $shop->load('deliveryman', 'members.customer', 'invites.shop.dealer', 'parent', 'branches');
        $shop->loadSum('pendingOrders as pending_orders_sum_total', 'total');
        $shop->loadSum('branches as branches_sum_balance', 'balance');

        $activeInvite = $shop->invites()
            ->valid()
            ->latest()
            ->with('shop.dealer')
            ->first();

        $visits = $shop->visits()
            ->with('user:id,name')
            ->latest('visited_at')
            ->limit(50)
            ->get();

        return Inertia::render('Dealer/Shops/Show', [
            'shop' => ShopResource::make($shop),
            'members' => ShopMemberResource::collection($shop->members),
            'visits' => ShopVisitResource::collection($visits),
            'activeInvite' => $activeInvite ? ShopInviteResource::make($activeInvite) : null,
            'canEdit' => $request->user()->can('update', $shop),
            'canInvite' => $request->user()->can('invite', $shop),
            'canUpdatePhoto' => $request->user()->can('updatePhoto', $shop),
            'canRecordVisit' => $request->user()->can('recordVisit', $shop),
        ]);
    }

    public function edit(Request $request, Shop $shop): Response
    {
        $this->authorize('update', $shop);

        $shop->loadCount('branches');

        return Inertia::render('Dealer/Shops/Edit', [
            'shop' => ShopResource::make($shop),
            'regions' => $this->regionOptions($request),
            'mapDefaults' => $this->mapDefaults($request),
            'parentShops' => $this->parentShopOptions(
                (int) $shop->dealer_id,
                excludeShopId: $shop->id,
                disableIfHasBranches: $shop->branches_count > 0,
            ),
        ]);
    }

    public function update(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        $this->authorize('update', $shop);

        $data = $request->safe()->except(['photo', 'remove_photo']);

        if ($request->has('parent_shop_id')) {
            $data['parent_shop_id'] = $request->integer('parent_shop_id') ?: null;
        }

        if ($request->hasFile('photo')) {
            if ($shop->photo) {
                Storage::disk('public')->delete($shop->photo);
            }
            $data['photo'] = $request->file('photo')->store("dealers/{$shop->dealer_id}/shops", 'public');
        } elseif ($request->boolean('remove_photo') && $shop->photo) {
            Storage::disk('public')->delete($shop->photo);
            $data['photo'] = null;
        }

        $shop->update($data);

        return redirect()
            ->route('dealer.shops.show', $shop)
            ->with('status', 'Mijoz yangilandi');
    }

    public function destroy(Request $request, Shop $shop): RedirectResponse
    {
        $this->authorize('delete', $shop);

        $shop->delete();

        return redirect()
            ->route('dealer.shops.index')
            ->with('status', 'Mijoz o\'chirildi');
    }

    public function updatePhoto(Request $request, Shop $shop): RedirectResponse
    {
        $this->authorize('updatePhoto', $shop);

        $request->validate([
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        if ($shop->photo) {
            Storage::disk('public')->delete($shop->photo);
        }

        $shop->update([
            'photo' => $request->file('photo')->store("dealers/{$shop->dealer_id}/shops", 'public'),
        ]);

        return back()->with('status', 'Rasm yangilandi');
    }

    public function destroyPhoto(Request $request, Shop $shop): RedirectResponse
    {
        $this->authorize('updatePhoto', $shop);

        if ($shop->photo) {
            Storage::disk('public')->delete($shop->photo);
            $shop->update(['photo' => null]);
        }

        return back()->with('status', 'Rasm o\'chirildi');
    }

    public function invite(Request $request, Shop $shop): RedirectResponse
    {
        $this->authorize('invite', $shop);

        $this->inviteService->createForShop($shop, $request->user());

        return back()->with('status', 'Taklif link yaratildi');
    }

    public function lookupInn(string $inn, Request $request, InnLookupServiceInterface $lookup): JsonResponse
    {
        $this->authorize('create', Shop::class);

        if (! preg_match('/^\d{9}$/', $inn)) {
            return response()->json(['message' => 'STIR 9 xonali raqam bo\'lishi kerak'], 422);
        }

        $dealerId = (int) $request->user()->dealer_id;

        $entries = DirectoryShop::query()
            ->forInn($inn)
            ->latest('id')
            ->limit(20)
            ->get();

        if ($entries->isNotEmpty()) {
            return response()->json([
                'shops' => $this->mapDirectoryForLookup($entries, $dealerId),
            ]);
        }

        $result = $lookup->lookup($inn);

        if ($result === null) {
            return response()->json(['message' => 'Xizmat vaqtincha ishlamayapti'], 503);
        }

        return response()->json($result);
    }

    public function reverseGeocode(Request $request, GeoResolver $geo, RegionMatcher $matcher): JsonResponse
    {
        $this->authorize('create', Shop::class);

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json(['message' => 'Koordinatalar noto\'g\'ri'], 422);
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;

        if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) {
            return response()->json(['message' => 'Koordinatalar diapazondan tashqari'], 422);
        }

        $country = $this->dealerCountry($request);
        $result = $geo->reverse($latF, $lngF, $this->langFor($country));

        return response()->json($this->enrichGeo($result, $country, $matcher));
    }

    /**
     * Reverse natijasini diller davlatiga moslaydi: region/district kanonik nom +
     * FK, hamda outside_uz ni diller davlatiga nisbatan qayta hisoblaydi.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function enrichGeo(array $result, ?Country $country, RegionMatcher $matcher): array
    {
        $result['region_id'] = null;
        $result['district_id'] = null;

        if ($country !== null) {
            // Viloyat/tuman nomini KANONIK yozuvga moslaymiz (alias + substring) —
            // "Башкортостан" → DB "Республика Башкортостан". Select avto-tanlanadi.
            $matched = $matcher->match($country, $result['region'] ?? null, $result['district'] ?? null);

            if ($matched['region'] !== null) {
                $result['region'] = $matched['region']->name;
                $result['region_id'] = $matched['region']->id;
            }

            if ($matched['district'] !== null) {
                $result['district'] = $matched['district']->name;
                $result['district_id'] = $matched['district']->id;
            }

            // "Tashqarida"mi — diller davlatiga nisbatan (UZ bbox emas).
            $cc = is_string($result['country_code'] ?? null) ? $result['country_code'] : null;
            $dealerCc = $country->geo_country_code ?? $country->code;

            if ($cc !== null && $dealerCc !== null) {
                $result['outside_uz'] = $cc !== $dealerCc;
            }
        }

        unset($result['country_code']);

        return $result;
    }

    /**
     * Diller davlatiga mos Nominatim tili — ruscha joy o'zbekcha qaytmasligi uchun.
     */
    private function langFor(?Country $country): string
    {
        return $country?->code === 'ru' ? 'ru,en' : 'uz,ru,en';
    }

    public function forwardGeocode(Request $request, ForwardGeocoderInterface $geocoder): JsonResponse
    {
        $this->authorize('create', Shop::class);

        $region = trim((string) $request->query('region', ''));
        $district = trim((string) $request->query('district', ''));

        if ($region === '') {
            return response()->json(['message' => 'Viloyat ko\'rsatilmagan'], 422);
        }

        $result = $geocoder->forward($region, $district !== '' ? $district : null);

        if ($result === null) {
            return response()->json(['message' => 'Joylashuv topilmadi'], 404);
        }

        return response()->json($result);
    }

    public function resolveMapLink(Request $request, GeoResolver $geo, RegionMatcher $matcher): JsonResponse
    {
        $this->authorize('create', Shop::class);

        $country = $this->dealerCountry($request);
        $result = $geo->resolveMapLink((string) $request->query('url', ''), $this->langFor($country));

        // Muvaffaqiyatli yechimni reverse-geocode kabi boyitamiz (FK + outside).
        if ($result['status'] === 200 && array_key_exists('lat', $result['body'])) {
            $result['body'] = $this->enrichGeo($result['body'], $country, $matcher);
        }

        return response()->json($result['body'], $result['status']);
    }

    public function lookupPhone(Request $request): JsonResponse
    {
        $this->authorize('create', Shop::class);

        $phone = (string) $request->query('phone', '');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 7) {
            return response()->json(['message' => 'Telefon raqam to\'liq kiritilishi kerak'], 422);
        }

        $tail = substr($digits, -9);
        $dealerId = (int) $request->user()->dealer_id;

        $entries = DirectoryShop::query()
            ->forPhoneTail($tail)
            ->latest('id')
            ->limit(20)
            ->get();

        if ($entries->isEmpty()) {
            return response()->json(['shops' => []], 404);
        }

        return response()->json([
            'shops' => $this->mapDirectoryForLookup($entries, $dealerId),
        ]);
    }

    /**
     * Bosh filial bo'lishi mumkin bo'lgan mijozlar (faqat top-level).
     *
     * @return array<int, array{id:int, name:string, inn:?string, region:?string, district:?string}>
     */
    private function parentShopOptions(int $dealerId, ?int $excludeShopId = null, bool $disableIfHasBranches = false): array
    {
        if ($disableIfHasBranches) {
            return [];
        }

        return Shop::query()
            ->forDealer($dealerId)
            ->whereNull('parent_shop_id')
            ->when($excludeShopId !== null, fn ($q) => $q->where('id', '!=', $excludeShopId))
            ->orderBy('name')
            ->get(['id', 'name', 'inn', 'region', 'district'])
            ->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'inn' => $shop->inn,
                'region' => $shop->region,
                'district' => $shop->district,
            ])
            ->all();
    }

    /**
     * @return array{
     *     id:int, name:string, legal_name:?string, phone:string, contact_person:?string,
     *     address:?string, landmark:?string, region:?string, district:?string, inn:?string,
     *     latitude:?float, longitude:?float, photo:?string, photo_url:?string, is_own:bool
     * }
     */
    /**
     * Spravochnik yozuvlarini lookup javobiga aylantiradi.
     * `is_own` — shu dealer allaqachon ushbu spravochnik yozuviga bog'langan shopga ega.
     *
     * @param  Collection<int, DirectoryShop>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function mapDirectoryForLookup($entries, int $dealerId): array
    {
        $ownedDirectoryIds = Shop::query()
            ->forDealer($dealerId)
            ->whereIn('directory_id', $entries->pluck('id'))
            ->pluck('directory_id')
            ->all();

        $ownedDirectoryIds = array_flip($ownedDirectoryIds);

        return $entries->map(fn (DirectoryShop $entry): array => [
            'id' => $entry->id,
            'name' => $entry->name,
            'legal_name' => $entry->legal_name,
            'phone' => $entry->phone,
            'contact_person' => $entry->contact_person,
            'address' => $entry->address,
            'landmark' => $entry->landmark,
            'region' => $entry->region,
            'district' => $entry->district,
            'inn' => $entry->inn,
            'latitude' => $entry->latitude,
            'longitude' => $entry->longitude,
            'photo' => $entry->photo,
            'photo_url' => $entry->photo ? Storage::disk('public')->url($entry->photo) : null,
            'is_own' => isset($ownedDirectoryIds[$entry->id]),
        ])->values()->all();
    }
}
