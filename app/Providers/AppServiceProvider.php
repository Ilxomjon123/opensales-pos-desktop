<?php

namespace App\Providers;

use App\Contracts\DatabaseDumperInterface;
use App\Contracts\ForwardGeocoderInterface;
use App\Contracts\InnLookupServiceInterface;
use App\Contracts\ReverseGeocoderInterface;
use App\Contracts\WebhookServiceInterface;
use App\Enums\FeatureFlag;
use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Services\NominatimForwardGeocoder;
use App\Services\NominatimReverseGeocoder;
use App\Services\OrginfoInnLookupService;
use App\Services\PostgresDumper;
use App\Services\Routing\CachedDistanceMatrixProvider;
use App\Services\Routing\DistanceMatrixProvider;
use App\Services\Routing\OpenRouteServiceProvider;
use App\Services\Routing\PersistentDistanceMatrixProvider;
use App\Services\Routing\YandexDistanceMatrixProvider;
use App\Services\WebhookService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            WebhookServiceInterface::class,
            WebhookService::class,
        );

        $this->app->bind(
            DatabaseDumperInterface::class,
            PostgresDumper::class,
        );

        $this->app->bind(
            InnLookupServiceInterface::class,
            OrginfoInnLookupService::class,
        );

        $this->app->bind(
            ReverseGeocoderInterface::class,
            NominatimReverseGeocoder::class,
        );

        $this->app->bind(
            ForwardGeocoderInterface::class,
            NominatimForwardGeocoder::class,
        );

        $this->app->singleton(DistanceMatrixProvider::class, function ($app): DistanceMatrixProvider {
            $providerName = (string) config('services.routing.provider', 'yandex');

            // L3: tashqi API — ROUTING_PROVIDER bo'yicha tanlanadi.
            // Faqat ma'lumot DB'da yo'q bo'lsa chaqiriladi.
            [$upstream, $mode] = match ($providerName) {
                'openrouteservice' => [
                    new OpenRouteServiceProvider(
                        http: $app->make(HttpFactory::class),
                        apiKey: (string) config('services.openrouteservice.api_key', ''),
                        endpoint: (string) config('services.openrouteservice.endpoint'),
                        profile: (string) config('services.openrouteservice.profile', 'driving-car'),
                    ),
                    'driving-car',
                ],
                default => [
                    new YandexDistanceMatrixProvider(
                        http: $app->make(HttpFactory::class),
                        apiKey: (string) config('services.yandex.routing_api_key', ''),
                        endpoint: (string) config('services.yandex.routing_endpoint'),
                        mode: (string) config('services.yandex.routing_mode', 'driving'),
                    ),
                    (string) config('services.yandex.routing_mode', 'driving'),
                ],
            };

            // L2: PostgreSQL — har bir juftlik 30 kun saqlanadi. Tizim
            // vaqt o'tishi bilan o'z bazasini yig'adi. Mode kalit qismi —
            // Yandex va ORS natijalari aralashmaydi.
            $persistent = new PersistentDistanceMatrixProvider(
                inner: $upstream,
                mode: $mode,
            );

            // L1: Redis — bir xil so'rovni qisqa muddatda takrorlash uchun.
            return new CachedDistanceMatrixProvider(
                inner: $persistent,
                cache: $app->make(CacheRepository::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerGates();
        $this->registerRateLimiters();
        $this->registerObservers();
        $this->registerFeatureFlags();
    }

    /**
     * Davlat bo'yicha funksiya bayroqlarini (Pennant) ro'yxatdan o'tkazish.
     * Scope — davlat kodi; saqlangan qiymat bo'lmasa standart holat qaytadi.
     */
    protected function registerFeatureFlags(): void
    {
        foreach (FeatureFlag::manageable() as $flag) {
            Feature::define($flag->value, fn (): bool => $flag->defaultEnabled());
        }
    }

    protected function registerObservers(): void
    {
        Order::observe(OrderObserver::class);
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('telegram-bot', function (object $job): Limit {
            $dealerId = method_exists($job, 'rateLimiterDealerId') ? (int) $job->rateLimiterDealerId() : 0;

            return Limit::perSecond(25)->by('telegram-bot:'.$dealerId);
        });
    }

    protected function registerGates(): void
    {
        Gate::define('viewAdmin', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('viewPulse', fn (User $user): bool => $user->isSuperAdmin());

        // POS modul uchun yagona ruxsat darvozalari (kassir + owner = sotuv,
        // faqat owner = sozlama va statistika).
        Gate::define('pos.access', fn (User $user): bool => $user->canRunPos());
        Gate::define('pos.manage', fn (User $user): bool => $user->canManagePos());
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! app()->isProduction());
        Model::unguard(false);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
