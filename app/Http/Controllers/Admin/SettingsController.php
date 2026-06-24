<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFeatureFlagRequest;
use App\Models\Country;
use App\Services\FeatureFlagService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function __construct(private readonly FeatureFlagService $flags) {}

    public function index(): Response
    {
        $countries = Country::query()->active()->ordered()->get(['code', 'name', 'native_name', 'flag']);

        return Inertia::render('Admin/Settings/Index', [
            'flags' => array_map(
                fn (FeatureFlag $flag): array => ['key' => $flag->value],
                FeatureFlag::manageable(),
            ),
            'countries' => $countries->map(fn (Country $c): array => [
                'code' => $c->code,
                'name' => $c->name,
                'native_name' => $c->native_name,
                'flag' => $c->flag,
            ])->all(),
            'matrix' => $this->flags->matrix($countries->pluck('code')->all()),
        ]);
    }

    public function updateFlag(UpdateFeatureFlagRequest $request): RedirectResponse
    {
        $this->flags->setForCountry(
            countryCode: $request->string('country')->toString(),
            flag: $request->flag(),
            enabled: $request->boolean('enabled'),
        );

        return back();
    }
}
