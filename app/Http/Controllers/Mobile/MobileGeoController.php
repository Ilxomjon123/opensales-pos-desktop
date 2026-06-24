<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\Geo\GeoCatalog;
use App\Services\GeoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobil ro'yxatdan o'tishda xarita uchun: koordinatadan viloyat/tuman va
 * qo'lda tanlash uchun viloyat+tuman ro'yxati. `country` (uz/ru) bo'yicha.
 */
final class MobileGeoController extends Controller
{
    public function __construct(
        private readonly GeoResolver $geo,
        private readonly GeoCatalog $catalog,
    ) {}

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $country = $this->country($request);
        $lang = $country?->code === 'ru' ? 'ru,en' : 'uz,ru,en';

        try {
            $r = $this->geo->reverse((float) $validated['lat'], (float) $validated['lng'], $lang);

            return response()->json([
                'region' => $r['region'],
                'district' => $r['district'],
                'address' => $r['address'],
                'outside_uz' => $r['outside_uz'],
            ]);
        } catch (\Throwable) {
            return response()->json(['region' => null, 'district' => null, 'address' => null, 'outside_uz' => false]);
        }
    }

    public function regions(Request $request): JsonResponse
    {
        $country = $this->country($request);

        return response()->json([
            'regions' => $country !== null ? $this->catalog->regionOptions($country) : [],
        ]);
    }

    private function country(Request $request): ?Country
    {
        $code = $request->string('country')->toString() ?: 'uz';

        return Country::query()->where('code', $code)->first()
            ?? Country::query()->where('code', 'uz')->first();
    }
}
