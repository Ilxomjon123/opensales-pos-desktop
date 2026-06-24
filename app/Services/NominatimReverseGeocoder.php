<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ReverseGeocoderInterface;
use App\Support\UzRegions;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * OpenStreetMap Nominatim orqali bepul reverse geocoding.
 * https://nominatim.org/release-docs/latest/api/Reverse/
 *
 * Rate limit: 1 req/sek (foydalanuvchi tomonidan trigger bo'lgani uchun yetadi).
 * Natija 30 kun kesh qilinadi (koordinata bir xil bo'lsa qayta so'ramaslik).
 */
final class NominatimReverseGeocoder implements ReverseGeocoderInterface
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    private const USER_AGENT = 'DealerBot/1.0 (https://github.com/dealer-bot)';

    private const CACHE_TTL_SECONDS = 2_592_000;

    private const HTTP_TIMEOUT_SECONDS = 6;

    // Nominatim usage policy: 1 req/sec absolute max.
    private const RATE_LIMIT_KEY = 'geocode:nominatim:rate';

    private const RATE_LIMIT_MAX_ATTEMPTS = 1;

    private const RATE_LIMIT_DECAY_SECONDS = 1;

    public function __construct(private readonly CacheRepository $cache) {}

    public function reverse(float $lat, float $lng, ?string $lang = null): array
    {
        $language = $lang ?? 'uz,ru,en';
        $key = sprintf('geocode:nominatim:v3:%s:%.5f:%.5f', $language, $lat, $lng);

        return $this->cache->remember(
            $key,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->fetch($lat, $lng, $language) ?? $this->empty(),
        );
    }

    /**
     * @return array{region: string|null, district: string|null, address: string|null, country_code: string|null}|null
     */
    private function fetch(float $lat, float $lng, string $language): ?array
    {
        // Nominatim 1 req/sec — limitga yetganda kutamiz, aks holda 429/ban xavfi
        $availableIn = RateLimiter::availableIn(self::RATE_LIMIT_KEY);

        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            if ($availableIn > 0 && $availableIn <= self::RATE_LIMIT_DECAY_SECONDS + 1) {
                usleep(($availableIn * 1_000_000) + 50_000);
            } else {
                Log::info('Nominatim rate-limit reached, skipping', [
                    'lat' => $lat,
                    'lng' => $lng,
                    'available_in' => $availableIn,
                ]);

                return null;
            }
        }

        RateLimiter::hit(self::RATE_LIMIT_KEY, self::RATE_LIMIT_DECAY_SECONDS);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => $language,
            ])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::ENDPOINT, [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'jsonv2',
                    'zoom' => 18,
                    'addressdetails' => 1,
                    'accept-language' => $language,
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('Nominatim reverse geocode failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $data = $response->json();
        $address = is_array($data['address'] ?? null) ? $data['address'] : [];

        if ($address === []) {
            return $this->empty();
        }

        return $this->normalize($address);
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array{region: string|null, district: string|null, address: string|null, country_code: string|null}
     */
    private function normalize(array $address): array
    {
        $rawRegion = $this->str($address, 'state')
            ?? $this->str($address, 'region')
            ?? $this->str($address, 'province');

        // Nominatim viloyatdagi shaharlarda `suburb` = mahalla (tuman emas) bo'ladi —
        // shu sababli har bir nomzod UzRegions ga moslashtirilib ko'riladi va birinchi
        // muvaffaqiyatli match olinadi (city_district Toshkent uchun, city/town shaharlar uchun).
        // Toshkent shahri uchun Nominatim `state` ni qaytarmaydi — `city = "Toshkent"` va
        // `county = "Яшнабадский район"` ko'rinishida keladi, shuning uchun region inference
        // tuman nomzodlaridan ham olinadi.
        $districtCandidates = array_filter([
            $this->str($address, 'city_district'),
            $this->str($address, 'county'),
            $this->str($address, 'town'),
            $this->str($address, 'city'),
            $this->str($address, 'municipality'),
            $this->str($address, 'suburb'),
        ]);

        $countryCodeRaw = $this->str($address, 'country_code');
        $countryCode = $countryCodeRaw !== null ? mb_strtolower($countryCodeRaw) : null;

        // O'zbekiston uchun kanonik viloyat/tuman nomiga moslash (UzRegions). Boshqa
        // davlatlar uchun Nominatim qaytargan nomlar o'zicha qaytadi — FK darajasida
        // saqlashda `RegionMatcher` davlat bo'yicha kanonik yozuvga moslaydi.
        if ($countryCode === null || $countryCode === 'uz') {
            [$matchedRegion, $matchedDistrict] = $this->matchUz($rawRegion, $districtCandidates);
        } else {
            $matchedRegion = $rawRegion;
            // RU va boshqalarda DB tuman = shahar bo'lgani uchun avval `city`/`town`
            // (масалан "Уфа"), keyin район/okrug nomzodlari.
            $matchedDistrict = $this->str($address, 'city')
                ?? $this->str($address, 'town')
                ?? $this->str($address, 'city_district')
                ?? $this->str($address, 'municipality')
                ?? $this->str($address, 'county')
                ?? $this->str($address, 'suburb');
        }

        $road = $this->str($address, 'road') ?? $this->str($address, 'pedestrian') ?? $this->str($address, 'neighbourhood');
        $house = $this->str($address, 'house_number');

        $street = match (true) {
            $road !== null && $house !== null => $road.', '.$house,
            $road !== null => $road,
            default => null,
        };

        return [
            'region' => $matchedRegion,
            'district' => $matchedDistrict,
            'address' => $street,
            'country_code' => $countryCode,
        ];
    }

    /**
     * O'zbekiston viloyat/tuman nomlarini kanonik ko'rinishga keltiradi.
     *
     * @param  array<int, string>  $districtCandidates
     * @return array{0: string|null, 1: string|null}
     */
    private function matchUz(?string $rawRegion, array $districtCandidates): array
    {
        $matchedRegion = $rawRegion !== null ? UzRegions::match($rawRegion, null)['region'] : null;
        $matchedDistrict = null;

        foreach ($districtCandidates as $candidate) {
            $matched = UzRegions::match($matchedRegion ?? $rawRegion, $candidate);

            if ($matched['district'] !== null) {
                $matchedRegion = $matched['region'];
                $matchedDistrict = $matched['district'];
                break;
            }

            if ($matchedRegion === null && $matched['region'] !== null) {
                $matchedRegion = $matched['region'];
            }
        }

        return [$matchedRegion, $matchedDistrict];
    }

    /**
     * @return array{region: null, district: null, address: null, country_code: null}
     */
    private function empty(): array
    {
        return ['region' => null, 'district' => null, 'address' => null, 'country_code' => null];
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function str(array $array, string $key): ?string
    {
        $value = $array[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
