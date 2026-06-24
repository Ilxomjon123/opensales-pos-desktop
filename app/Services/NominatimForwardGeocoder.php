<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ForwardGeocoderInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * OpenStreetMap Nominatim search API orqali bepul forward geocoding.
 * https://nominatim.org/release-docs/latest/api/Search/
 *
 * Region/district nomidan markaziy nuqtani topib, Leaflet uchun mos zoom
 * darajasini tanlaydi. Natija 30 kun kesh qilinadi.
 */
final class NominatimForwardGeocoder implements ForwardGeocoderInterface
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    private const USER_AGENT = 'DealerBot/1.0 (https://github.com/dealer-bot)';

    private const CACHE_TTL_SECONDS = 2_592_000;

    private const HTTP_TIMEOUT_SECONDS = 6;

    // Reverse va Forward bir xil Nominatim endpoint'iga uradi — bir umumiy bucket
    private const RATE_LIMIT_KEY = 'geocode:nominatim:rate';

    private const RATE_LIMIT_MAX_ATTEMPTS = 1;

    private const RATE_LIMIT_DECAY_SECONDS = 1;

    public function __construct(private readonly CacheRepository $cache) {}

    public function forward(string $region, ?string $district = null): ?array
    {
        $region = trim($region);

        if ($region === '') {
            return null;
        }

        $district = $district !== null && trim($district) !== '' ? trim($district) : null;

        $key = sprintf('geocode:nominatim:fwd:v1:%s', md5(mb_strtolower($region.'|'.($district ?? ''))));

        $value = $this->cache->remember(
            $key,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->fetch($region, $district) ?? ['miss' => true],
        );

        if (isset($value['miss'])) {
            return null;
        }

        /** @var array{lat: float, lng: float, zoom: int} $value */
        return $value;
    }

    /**
     * @return array{lat: float, lng: float, zoom: int}|null
     */
    private function fetch(string $region, ?string $district): ?array
    {
        $query = $this->buildQuery($region, $district);

        $availableIn = RateLimiter::availableIn(self::RATE_LIMIT_KEY);

        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            if ($availableIn > 0 && $availableIn <= self::RATE_LIMIT_DECAY_SECONDS + 1) {
                usleep(($availableIn * 1_000_000) + 50_000);
            } else {
                Log::info('Nominatim rate-limit reached (forward), skipping', [
                    'query' => $query,
                    'available_in' => $availableIn,
                ]);

                return null;
            }
        }

        RateLimiter::hit(self::RATE_LIMIT_KEY, self::RATE_LIMIT_DECAY_SECONDS);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => 'uz,ru,en',
            ])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::ENDPOINT, [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'uz',
                    'accept-language' => 'uz,ru,en',
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('Nominatim forward geocode failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $data = $response->json();

        if (! is_array($data) || $data === []) {
            return null;
        }

        $first = $data[0] ?? null;

        if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
            return null;
        }

        $lat = (float) $first['lat'];
        $lng = (float) $first['lon'];

        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'zoom' => $district !== null ? 12 : 9,
        ];
    }

    private function buildQuery(string $region, ?string $district): string
    {
        $cleanRegion = $this->stripSuffixes($region);

        if ($district === null) {
            return $cleanRegion.', Uzbekistan';
        }

        $cleanDistrict = $this->stripSuffixes($district);

        return $cleanDistrict.', '.$cleanRegion.', Uzbekistan';
    }

    /**
     * "Toshkent shahri" -> "Toshkent", "Bektemir tumani" -> "Bektemir".
     * Nominatim ko'pincha lokal "tumani"/"shahri" so'zlarini tushunmaydi.
     */
    private function stripSuffixes(string $name): string
    {
        $suffixes = [' tumani', ' shahri', ' viloyati', ' Respublikasi'];

        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return mb_substr($name, 0, mb_strlen($name) - mb_strlen($suffix));
            }
        }

        return $name;
    }
}
