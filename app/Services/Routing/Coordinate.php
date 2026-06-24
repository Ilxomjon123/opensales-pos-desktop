<?php

declare(strict_types=1);

namespace App\Services\Routing;

/**
 * Immutable geokoordinata. Routing servislari shu obyekt orqali ishlaydi.
 */
final readonly class Coordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    public function toString(): string
    {
        return sprintf('%.7f,%.7f', $this->latitude, $this->longitude);
    }

    public function equals(self $other): bool
    {
        return abs($this->latitude - $other->latitude) < 1e-7
            && abs($this->longitude - $other->longitude) < 1e-7;
    }
}
