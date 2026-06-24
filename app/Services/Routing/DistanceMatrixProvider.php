<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Exceptions\Domain\RoutingException;

interface DistanceMatrixProvider
{
    /**
     * Berilgan koordinatalar uchun N×N masofa matritsasini qaytaradi.
     *
     * @param  list<Coordinate>  $points
     *
     * @throws RoutingException
     */
    public function matrix(array $points): DistanceMatrix;
}
