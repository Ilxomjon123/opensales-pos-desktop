<?php

declare(strict_types=1);

namespace App\Services\Routing;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * DistanceMatrixProvider'ni Redis kesh bilan o'rab oladi.
 *
 * Kesh kaliti: koordinatalar ro'yxati hashi. Bir xil nuqtalar to'plami
 * → bitta kesh kaliti. Yangi nuqta qo'shilsa, kalit o'zgaradi.
 */
final readonly class CachedDistanceMatrixProvider implements DistanceMatrixProvider
{
    private const int TTL_SECONDS = 7200;

    public function __construct(
        private DistanceMatrixProvider $inner,
        private CacheRepository $cache,
    ) {}

    public function matrix(array $points): DistanceMatrix
    {
        $key = $this->buildKey($points);
        $cached = $this->cache->get($key);

        if (is_array($cached) && isset($cached['distance'], $cached['duration'])) {
            return new DistanceMatrix(
                points: $points,
                distanceMeters: $cached['distance'],
                durationSeconds: $cached['duration'],
            );
        }

        $matrix = $this->inner->matrix($points);

        $this->cache->put($key, [
            'distance' => $matrix->distanceMeters,
            'duration' => $matrix->durationSeconds,
        ], self::TTL_SECONDS);

        return $matrix;
    }

    /**
     * @param  list<Coordinate>  $points
     */
    private function buildKey(array $points): string
    {
        $signature = implode(';', array_map(fn (Coordinate $c) => $c->toString(), $points));

        return 'routing:matrix:'.sha1($signature);
    }
}
