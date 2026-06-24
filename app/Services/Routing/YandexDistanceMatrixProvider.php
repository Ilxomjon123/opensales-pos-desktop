<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Exceptions\Domain\RoutingException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Yandex Routing Distance Matrix API orqali real yo'l masofasini hisoblaydi.
 *
 * Yandex limiti: bitta so'rovda maksimal 10 origin va 10 destination.
 * Shuning uchun katta matritsa kichik chunklarga bo'linadi.
 *
 * @see https://yandex.com/maps-api/products/distance-matrix-api
 */
final readonly class YandexDistanceMatrixProvider implements DistanceMatrixProvider
{
    private const int CHUNK_SIZE = 10;

    public function __construct(
        private HttpFactory $http,
        private string $apiKey,
        private string $endpoint,
        private string $mode,
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

        $distance = array_fill(0, $n, array_fill(0, $n, 0));
        $duration = array_fill(0, $n, array_fill(0, $n, 0));

        $chunks = array_chunk(range(0, $n - 1), self::CHUNK_SIZE);

        foreach ($chunks as $originChunk) {
            foreach ($chunks as $destChunk) {
                $this->fetchChunk($points, $originChunk, $destChunk, $distance, $duration);
            }
        }

        return new DistanceMatrix($points, $distance, $duration);
    }

    /**
     * @param  list<Coordinate>  $points
     * @param  list<int>  $originIndexes
     * @param  list<int>  $destIndexes
     * @param  array<int, array<int, int>>  $distance
     * @param  array<int, array<int, int>>  $duration
     */
    private function fetchChunk(
        array $points,
        array $originIndexes,
        array $destIndexes,
        array &$distance,
        array &$duration,
    ): void {
        $origins = implode('|', array_map(fn (int $i) => $points[$i]->toString(), $originIndexes));
        $destinations = implode('|', array_map(fn (int $i) => $points[$i]->toString(), $destIndexes));

        $response = $this->http
            ->timeout(15)
            ->retry(2, 200, throw: false)
            ->get($this->endpoint, [
                'apikey' => $this->apiKey,
                'origins' => $origins,
                'destinations' => $destinations,
                'mode' => $this->mode,
            ]);

        if (! $response->successful()) {
            $body = $response->body();
            Log::warning('Yandex Distance Matrix HTTP error', [
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 500),
            ]);

            throw RoutingException::apiError("HTTP {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['rows']) || ! is_array($data['rows'])) {
            throw RoutingException::apiError('Javob formati noto\'g\'ri');
        }

        foreach ($data['rows'] as $rowIdx => $row) {
            if (! isset($row['elements']) || ! is_array($row['elements'])) {
                continue;
            }

            $globalOrigin = $originIndexes[$rowIdx] ?? null;

            if ($globalOrigin === null) {
                continue;
            }

            foreach ($row['elements'] as $colIdx => $element) {
                $globalDest = $destIndexes[$colIdx] ?? null;

                if ($globalDest === null) {
                    continue;
                }

                $status = $element['status'] ?? 'OK';

                if ($status !== 'OK') {
                    continue;
                }

                $distance[$globalOrigin][$globalDest] = (int) ($element['distance']['value'] ?? 0);
                $duration[$globalOrigin][$globalDest] = (int) ($element['duration']['value'] ?? 0);
            }
        }
    }
}
