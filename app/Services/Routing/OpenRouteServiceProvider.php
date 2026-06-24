<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Exceptions\Domain\RoutingException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouteService Matrix API — bepul, kuniga 2000 so'rov.
 *
 * Endpoint: POST https://api.openrouteservice.org/v2/matrix/{profile}
 * Body: {"locations": [[lng, lat], ...], "metrics": ["distance", "duration"]}
 *
 * Diqqat: ORS koordinatani [lng, lat] tartibida kutadi (Yandex'da [lat, lng]).
 * Limit: bitta so'rovda 50 ta nuqta.
 *
 * @see https://openrouteservice.org/dev/#/api-docs/v2/matrix
 */
final readonly class OpenRouteServiceProvider implements DistanceMatrixProvider
{
    private const int MAX_LOCATIONS = 50;

    public function __construct(
        private HttpFactory $http,
        private string $apiKey,
        private string $endpoint,
        private string $profile,
    ) {}

    public function matrix(array $points): DistanceMatrix
    {
        if ($this->apiKey === '') {
            throw RoutingException::apiKeyMissing();
        }

        $n = count($points);

        if ($n === 0) {
            throw RoutingException::emptyRoute();
        }

        if ($n > self::MAX_LOCATIONS) {
            throw RoutingException::tooManyPoints($n, self::MAX_LOCATIONS);
        }

        $locations = array_map(
            fn (Coordinate $c) => [$c->longitude, $c->latitude],
            $points,
        );

        $url = rtrim($this->endpoint, '/').'/'.$this->profile;

        $response = $this->http
            ->withHeaders([
                'Authorization' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(20)
            ->retry(2, 300, throw: false)
            ->post($url, [
                'locations' => $locations,
                'metrics' => ['distance', 'duration'],
                'units' => 'm',
            ]);

        if (! $response->successful()) {
            $body = $response->body();
            Log::warning('OpenRouteService HTTP error', [
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 500),
            ]);

            throw RoutingException::apiError("HTTP {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['distances'], $data['durations'])) {
            throw RoutingException::apiError('Javob formati noto\'g\'ri');
        }

        $distance = array_fill(0, $n, array_fill(0, $n, 0));
        $duration = array_fill(0, $n, array_fill(0, $n, 0));

        foreach ($data['distances'] as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $j => $value) {
                if ($value === null) {
                    continue;
                }

                $distance[$i][$j] = (int) round((float) $value);
            }
        }

        foreach ($data['durations'] as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $j => $value) {
                if ($value === null) {
                    continue;
                }

                $duration[$i][$j] = (int) round((float) $value);
            }
        }

        return new DistanceMatrix($points, $distance, $duration);
    }
}
