<?php

namespace App\Http\Middleware;

use App\Enums\Currency;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Country;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\User;
use App\Services\CourierCashService;
use App\Services\ImpersonationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $impersonation = app(ImpersonationService::class);
        $impersonator = $impersonation->isImpersonating()
            ? User::query()->find($impersonation->impersonatorId())
            : null;
        $supportedLocales = config('locales.supported');
        $locales = is_array($supportedLocales) ? array_values($supportedLocales) : [];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'project' => [
                'name' => (string) config('project.name'),
                'url' => (string) config('project.url'),
                'support_telegram' => config('project.support_telegram'),
                'version' => (string) config('project.version'),
            ],
            'auth' => [
                'user' => $user,
                'role' => $user?->role?->value,
                'dealer_id' => $user?->dealer_id,
                'permissions' => [
                    'pos_access' => $user?->canRunPos() ?? false,
                    'pos_manage' => $user?->canManagePos() ?? false,
                    'manage_inventory' => $user?->canManageInventory() ?? false,
                ],
            ],
            // Dealer darajasidagi valyuta — pul formatlash uchun (frontend useCurrency).
            'currency' => fn () => $this->currencyFor($user),
            // Dealer davlati — telefon input default country uchun (frontend PhoneInput).
            'country' => fn () => $this->countryFor($user),
            'impersonation' => $impersonator !== null ? [
                'active' => true,
                'impersonator' => [
                    'id' => $impersonator->id,
                    'name' => $impersonator->name,
                ],
                'as' => [
                    'id' => $user?->id,
                    'name' => $user?->name,
                    'role' => $user?->role?->value,
                ],
            ] : null,
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Lazy — landing route middleware (SetLandingLocale) global SetLocale'dan
            // keyin ishlaydi; til render vaqtida hal qilinsin, erta emas.
            'locale' => fn () => App::getLocale(),
            'locales' => $locales,
            'badges' => fn () => [
                'new_orders' => $this->newOrdersCount($user),
                'route_today_remaining' => $this->routeTodayRemainingCount($user),
                'carry_orders' => $this->carryOrdersCount($user),
                'courier_cash_balance' => $this->courierCashBalance($user),
            ],
        ];
    }

    /**
     * Joriy foydalanuvchi dilleri valyutasi (yo'q bo'lsa — UZS).
     *
     * @return array{code: string, symbol: string}
     */
    private function currencyFor(?User $user): array
    {
        $currency = Currency::UZS;

        if ($user?->dealer_id !== null) {
            $raw = Dealer::query()->whereKey($user->dealer_id)->value('currency');
            $currency = $raw instanceof Currency
                ? $raw
                : (Currency::tryFrom((string) $raw) ?? Currency::UZS);
        }

        return ['code' => $currency->value, 'symbol' => $currency->symbol()];
    }

    /**
     * Joriy foydalanuvchi dilleri davlati — telefon input default country uchun.
     * Diller yoki davlat topilmasa — O'zbekiston (uz).
     *
     * @return array{code: string, prefix: string, digits: int}
     */
    private function countryFor(?User $user): array
    {
        $default = ['code' => 'uz', 'prefix' => '+998', 'digits' => 9];

        if ($user?->dealer_id === null) {
            return $default;
        }

        $countryId = Dealer::query()->whereKey($user->dealer_id)->value('country_id');

        if ($countryId === null) {
            return $default;
        }

        $country = Country::query()->find($countryId);

        if ($country === null) {
            return $default;
        }

        return [
            'code' => (string) $country->code,
            'prefix' => (string) $country->phone_prefix,
            'digits' => (int) $country->phone_digits,
        ];
    }

    /**
     * "Buyurtmalar" menyusi — yangi (PENDING) buyurtmalar soni.
     * Dealer/warehouse: dillerning barcha PENDING'lari.
     * Deliveryman: o'ziga biriktirilgan + hech kimga biriktirilmagan PENDING'lar.
     */
    private function newOrdersCount(?User $user): int
    {
        if (! $this->isDealerScope($user)) {
            return 0;
        }

        return Order::query()
            ->where('dealer_id', $user->dealer_id)
            ->shopChannel()
            ->where('status', OrderStatus::PENDING)
            ->when($user->role === UserRole::DELIVERYMAN, fn ($q) => $q->where(
                fn ($sub) => $sub->where('deliveryman_id', $user->id)->orWhereNull('deliveryman_id')
            ))
            ->count();
    }

    /**
     * "Bugungi marshrut" menyusi — bugungi reja bo'yicha qolgan
     * (PENDING / ASSEMBLING / DELIVERING) buyurtmalar + bugun yetkazilgan
     * yoki qabul qilingan, lekin skladga vozvrati kutilayotgan buyurtmalar.
     * Sahifa kartochkalari bilan moslashadi.
     */
    private function routeTodayRemainingCount(?User $user): int
    {
        if (! $this->isDealerScope($user)) {
            return 0;
        }

        $today = CarbonImmutable::today()->toDateString();
        $scope = fn ($q) => $q
            ->where('dealer_id', $user->dealer_id)
            ->where('channel', '!=', OrderChannel::MARKETPLACE->value)
            ->when($user->role === UserRole::DELIVERYMAN, fn ($qq) => $qq->where('deliveryman_id', $user->id));

        $remaining = Order::query()
            ->tap($scope)
            ->whereIn('status', [OrderStatus::PENDING, OrderStatus::ASSEMBLING, OrderStatus::DELIVERING])
            ->whereDate('created_at', '<=', $today)
            ->count();

        $pendingReturn = Order::query()
            ->tap($scope)
            ->whereIn('status', [OrderStatus::DELIVERED, OrderStatus::RECEIVED])
            ->hasPendingReturn()
            ->where(function ($q) use ($today): void {
                $q->where(function ($qq) use ($today): void {
                    $qq->where('status', OrderStatus::DELIVERED)
                        ->whereDate('delivered_at', $today);
                })->orWhere(function ($qq) use ($today): void {
                    $qq->where('status', OrderStatus::RECEIVED)
                        ->whereDate('received_at', $today);
                });
            })
            ->count();

        return $remaining + $pendingReturn;
    }

    /**
     * "Yetkazib beruvchidagi qoldiq" menyusi — yetkazib beruvchilar
     * qo'lidagi qoldiq (carry) bor buyurtmalar soni. Owner/sklad uchun
     * barcha yetkazib beruvchilar, deliveryman uchun faqat o'zi.
     */
    private function carryOrdersCount(?User $user): int
    {
        if (! $this->isDealerScope($user)) {
            return 0;
        }

        return Order::query()
            ->where('dealer_id', $user->dealer_id)
            ->whereIn('status', [OrderStatus::DELIVERING, OrderStatus::DELIVERED, OrderStatus::RECEIVED])
            ->whereNotNull('deliveryman_id')
            ->hasPendingReturn()
            ->when($user->role === UserRole::DELIVERYMAN, fn ($q) => $q->where('deliveryman_id', $user->id))
            ->count();
    }

    /**
     * "Yetkazuvchidagi naqd" menyusi — yetkazib beruvchi qo'lidagi
     * topshirilmagan naqd pul jami summasi (so'm).
     * Owner/warehouse: tashkilotdagi barcha kuryerlar yig'indisi.
     * Deliveryman: o'zining balansi.
     */
    private function courierCashBalance(?User $user): int
    {
        if (! $this->isDealerScope($user)) {
            return 0;
        }

        $service = app(CourierCashService::class);

        if ($user->role === UserRole::DELIVERYMAN) {
            return $service->balanceFor($user);
        }

        return (int) array_sum($service->balancesForDealer((int) $user->dealer_id));
    }

    /**
     * @phpstan-assert-if-true !null $user
     */
    private function isDealerScope(?User $user): bool
    {
        return $user !== null
            && $user->dealer_id !== null
            && in_array($user->role, [UserRole::DEALER, UserRole::WAREHOUSE, UserRole::DELIVERYMAN], true);
    }
}
