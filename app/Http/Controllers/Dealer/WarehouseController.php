<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Contracts\ReverseGeocoderInterface;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Dealer;
use App\Support\MapLinkParser;
use GuzzleHttp\TransferStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Diller ombori koordinatasini sozlash. Marshrut optimizatsiyasi shu
 * koordinatadan boshlanadi.
 */
final class WarehouseController extends Controller
{
    public function show(Request $request): Response
    {
        $dealer = $this->dealer($request);

        return Inertia::render('Dealer/Settings/Warehouse', [
            'warehouse' => [
                'latitude' => $dealer->warehouse_latitude !== null ? (float) $dealer->warehouse_latitude : null,
                'longitude' => $dealer->warehouse_longitude !== null ? (float) $dealer->warehouse_longitude : null,
                'address' => $dealer->warehouse_address,
                'map_provider' => $dealer->warehouse_map_provider,
            ],
            'mapDefaults' => $this->mapDefaults($dealer),
        ]);
    }

    /**
     * Xarita default markazi — diller davlatiga mos (RU diller → Rossiya).
     *
     * @return array{lat: float, lng: float, zoom: int}|null
     */
    private function mapDefaults(Dealer $dealer): ?array
    {
        $country = $dealer->country
            ?? Country::query()->where('code', 'uz')->first();

        if ($country === null || $country->default_latitude === null || $country->default_longitude === null) {
            return null;
        }

        return [
            'lat' => (float) $country->default_latitude,
            'lng' => (float) $country->default_longitude,
            'zoom' => (int) $country->default_zoom,
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $dealer = $this->dealer($request);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:500'],
            'map_provider' => ['nullable', 'string', 'in:yandex,google,manual'],
        ]);

        $dealer->update([
            'warehouse_latitude' => round((float) $validated['latitude'], 7),
            'warehouse_longitude' => round((float) $validated['longitude'], 7),
            'warehouse_address' => $validated['address'] ?? null,
            'warehouse_map_provider' => $validated['map_provider'] ?? 'manual',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ombor manzili saqlandi']);

        return back();
    }

    /**
     * Map linkdan koordinatalarni ajratib olish (Shop bilan bir xil pattern).
     */
    public function resolveMapLink(Request $request, ReverseGeocoderInterface $geocoder): JsonResponse
    {
        $dealer = $this->dealer($request);
        $lang = ($dealer->country?->code) === 'ru' ? 'ru,en' : 'uz,ru,en';

        $url = trim((string) $request->query('url', ''));

        if ($url === '') {
            return response()->json(['message' => 'Link kiritilmagan'], 422);
        }

        $coords = MapLinkParser::parse($url);

        if ($coords !== null) {
            return response()->json($this->withGeocode($coords, $geocoder, $lang));
        }

        if (! preg_match('#^https?://#i', $url) || strlen($url) > 2000) {
            return response()->json(['message' => 'Linkdan koordinatalar topilmadi'], 404);
        }

        $effectiveUri = $url;

        try {
            Http::withUserAgent('Mozilla/5.0 (Linux; Android 11) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36')
                ->withOptions([
                    'timeout' => 5,
                    'connect_timeout' => 3,
                    'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                    'on_stats' => function (TransferStats $stats) use (&$effectiveUri): void {
                        $effectiveUri = (string) $stats->getEffectiveUri();
                    },
                ])
                ->get($url);
        } catch (\Throwable) {
            return response()->json(['message' => "Linkni ochib bo'lmadi"], 503);
        }

        $coords = MapLinkParser::parse($effectiveUri);

        if ($coords === null) {
            return response()->json(['message' => 'Linkdan koordinatalar topilmadi'], 404);
        }

        return response()->json($this->withGeocode($coords, $geocoder, $lang));
    }

    /**
     * @param  array{lat: float, lng: float}  $coords
     * @return array{lat: float, lng: float, region: string|null, district: string|null, address: string|null}
     */
    private function withGeocode(array $coords, ReverseGeocoderInterface $geocoder, ?string $lang = null): array
    {
        $address = $geocoder->reverse($coords['lat'], $coords['lng'], $lang);

        return [
            ...$coords,
            'region' => $address['region'],
            'district' => $address['district'],
            'address' => $address['address'],
        ];
    }

    private function dealer(Request $request): Dealer
    {
        $dealer = $request->user()?->dealer;

        abort_if($dealer === null, 403, 'Diller topilmadi');

        return $dealer;
    }
}
