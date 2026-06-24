<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ReverseGeocoderInterface;
use App\Support\MapLinkParser;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Http;

/**
 * Koordinata ↔ manzil va xarita-link yechimi. Dealer va Admin formalarida
 * bir xil ishlatiladi (LocationPicker reverse-geocode + map-link).
 */
final class GeoResolver
{
    private const ALLOWED_SHORTENER_HOSTS = [
        'maps.app.goo.gl', 'goo.gl', 'g.co',
        'maps.yandex.ru', 'maps.yandex.uz', 'maps.yandex.com',
    ];

    public function __construct(private readonly ReverseGeocoderInterface $geocoder) {}

    /**
     * Koordinata bo'yicha viloyat/tuman/manzil.
     *
     * @return array{lat: float, lng: float, region: string|null, district: string|null, address: string|null, country_code: string|null, outside_uz: bool}
     */
    public function reverse(float $lat, float $lng, ?string $lang = null): array
    {
        $address = $this->geocoder->reverse($lat, $lng, $lang);

        return [
            'lat' => $lat,
            'lng' => $lng,
            'region' => $address['region'],
            'district' => $address['district'],
            'address' => $address['address'],
            'country_code' => $address['country_code'],
            'outside_uz' => $this->isOutsideUzbekistan($lat, $lng, $address['country_code']),
        ];
    }

    /**
     * Google/Yandex xarita linkidan koordinata + manzilni yechadi.
     * HTTP status + javob tanasini qaytaradi (controller faqat json qiladi).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resolveMapLink(string $url, ?string $lang = null): array
    {
        $url = trim($url);

        if ($url === '') {
            return ['status' => 422, 'body' => ['message' => 'Link kiritilmagan']];
        }

        // Avval to'g'ridan-to'g'ri linkdan parse (qisqartirilmagan URL).
        $coords = MapLinkParser::parse($url);

        if ($coords !== null) {
            return ['status' => 200, 'body' => $this->reverse($coords['lat'], $coords['lng'], $lang)];
        }

        if (! preg_match('#^https?://#i', $url) || strlen($url) > 2000) {
            return ['status' => 404, 'body' => ['message' => 'Linkdan koordinatalar topilmadi']];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $isMapsDomain = preg_match('#^(www\.)?(google|yandex)\.[a-z.]+$#i', $host) === 1;
        $isShortener = in_array($host, self::ALLOWED_SHORTENER_HOSTS, true);

        if (! $isMapsDomain && ! $isShortener) {
            return ['status' => 422, 'body' => ['message' => 'Faqat Google yoki Yandex map linklari']];
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
            return ['status' => 503, 'body' => ['message' => "Linkni ochib bo'lmadi"]];
        }

        $coords = MapLinkParser::parse($effectiveUri);

        if ($coords === null) {
            return ['status' => 404, 'body' => ['message' => 'Linkdan koordinatalar topilmadi']];
        }

        return ['status' => 200, 'body' => $this->reverse($coords['lat'], $coords['lng'], $lang)];
    }

    /**
     * country_code bo'lsa unga ishonamiz; aks holda O'zbekiston bbox bilan tekshiramiz.
     */
    private function isOutsideUzbekistan(float $lat, float $lng, ?string $countryCode): bool
    {
        if ($countryCode !== null) {
            return $countryCode !== 'uz';
        }

        // O'zbekiston taxminiy bbox: lat 37.18..45.59, lng 55.99..73.13
        return $lat < 37.0 || $lat > 45.7 || $lng < 55.9 || $lng > 73.2;
    }
}
