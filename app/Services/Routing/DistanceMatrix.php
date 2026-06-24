<?php

declare(strict_types=1);

namespace App\Services\Routing;

/**
 * N×N matrix: har bir element {distance_meters, duration_seconds}.
 *
 * $points indeksi = matrix indeksi. distance[i][j] = i nuqtadan j nuqtaga.
 */
final readonly class DistanceMatrix
{
    /**
     * @param  list<Coordinate>  $points
     * @param  array<int, array<int, int>>  $distanceMeters
     * @param  array<int, array<int, int>>  $durationSeconds
     */
    public function __construct(
        public array $points,
        public array $distanceMeters,
        public array $durationSeconds,
    ) {}

    public function size(): int
    {
        return count($this->points);
    }

    public function distance(int $from, int $to): int
    {
        return $this->distanceMeters[$from][$to] ?? 0;
    }

    public function duration(int $from, int $to): int
    {
        return $this->durationSeconds[$from][$to] ?? 0;
    }
}
