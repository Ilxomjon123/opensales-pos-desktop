<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterDealerAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterDealerRequest;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class DealerRegistrationController extends Controller
{
    public function __construct(
        private readonly RegisterDealerAction $registerDealer,
    ) {}

    public function create(): Response
    {
        $shape = fn (array $plans): array => collect($plans)
            ->map(fn (int $amount, string $key): array => ['key' => $key, 'amount' => $amount])
            ->values()
            ->all();

        // Har davlat uchun o'z valyutasidagi tariflar.
        $plansByCountry = collect((array) config('tariffs.plans_by_country'))
            ->map(fn (array $plans): array => $shape($plans))
            ->all();

        return Inertia::render('auth/Register', [
            'plans' => $shape((array) config('tariffs.plans')),
            'plansByCountry' => $plansByCountry,
            'trialDays' => (int) config('tariffs.trial_days', 14),
            'countries' => CountryResource::collection(Country::query()->active()->ordered()->get())->resolve(),
        ]);
    }

    public function store(RegisterDealerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Tanlangan tarif narxi diller davlati valyutasida olinadi.
        $countryCode = isset($data['country_id'])
            ? Country::query()->whereKey($data['country_id'])->value('code')
            : null;
        $plans = (array) config('tariffs.plans_by_country.'.$countryCode, config('tariffs.plans'));
        $fixedAmount = (int) ($plans[$data['commission_type']] ?? 0);
        $trialDays = (int) config('tariffs.trial_days', 14);

        // Diller bot tokensiz yaratiladi — tokenni keyin onboarding'da ichkarida qo'shadi.
        $dealer = $this->registerDealer->execute([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'country_id' => $data['country_id'] ?? null,
            'is_active' => true,
            'is_self_registered' => true,
            'trial_ends_at' => now()->addDays($trialDays),
            'commission_type' => $data['commission_type'],
            'fixed_commission_amount' => $fixedAmount,
        ]);

        $owner = User::query()
            ->where('dealer_id', $dealer->id)
            ->where('role', UserRole::DEALER)
            ->firstOrFail();

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('dealer.stats.index');
    }
}
