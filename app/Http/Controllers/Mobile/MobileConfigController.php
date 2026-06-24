<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\FeatureFlagService;
use App\Services\GeoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobil ilova uchun ochiq (auth talab qilinmaydi) config — joriy davlatga
 * mos funksiya bayroqlari. Davlat `country` (kod) yoki joriy koordinata
 * (`lat`+`lng`, teskari geokodlash) orqali aniqlanadi. Topilmasa — default
 * faol davlat (odatda 'uz').
 */
final class MobileConfigController extends Controller
{
    public function __construct(
        private readonly FeatureFlagService $flags,
        private readonly GeoResolver $geo,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:8'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $country = $this->resolveCountry($validated);

        return response()->json([
            'country' => $country->code,
            'features' => $this->flags->mobileConfig($country->code),
        ]);
    }

    /**
     * @param  array{country?: string|null, lat?: float|null, lng?: float|null}  $input
     */
    private function resolveCountry(array $input): Country
    {
        $code = $input['country'] ?? null;

        // Koordinata berilgan bo'lsa — teskari geokodlab davlat kodini olamiz.
        if ($code === null && isset($input['lat'], $input['lng'])) {
            try {
                $r = $this->geo->reverse((float) $input['lat'], (float) $input['lng']);
                $code = $r['country_code'];
            } catch (\Throwable) {
                $code = null;
            }
        }

        return $this->matchCountry($code) ?? $this->defaultCountry();
    }

    private function matchCountry(?string $code): ?Country
    {
        if ($code === null || $code === '') {
            return null;
        }

        $code = strtolower($code);

        return Country::query()->active()
            ->where(fn ($q) => $q->where('code', $code)->orWhere('geo_country_code', $code))
            ->first();
    }

    private function defaultCountry(): Country
    {
        return Country::query()->active()->ordered()->first()
            ?? Country::query()->where('code', 'uz')->firstOrFail();
    }
}
