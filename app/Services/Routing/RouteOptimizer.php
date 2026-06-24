<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Exceptions\Domain\RoutingException;

/**
 * Nearest-Neighbor TSP yechimi.
 *
 * Murakkabligi: O(N²). 50 nuqta uchun ~2500 amaliyot — ~5ms.
 * Optimal yechim emas, lekin amaliyotda real masofa uchun
 * o'rtacha 10-20% farq qiladi. Bu mashinaga yuklash uchun yetarli.
 *
 * Algoritm:
 *  1. Ombordan boshlaymiz (index 0).
 *  2. Hozirgi nuqtadan eng yaqin tashrif buyurilmagan nuqtani tanlaymiz.
 *  3. Barcha nuqtalar tugaguncha takrorlaymiz.
 *  4. Yetkazib berish tartibi tayyor → teskarisi yuklash tartibi.
 */
final readonly class RouteOptimizer
{
    public function __construct(private DistanceMatrixProvider $provider) {}

    /**
     * @template T
     *
     * @param  RouteStop<T>  $warehouse
     * @param  list<RouteStop<T>>  $stops
     */
    public function optimize(RouteStop $warehouse, array $stops, int $maxStops = 50): OptimizedRoute
    {
        if ($stops === []) {
            throw RoutingException::emptyRoute();
        }

        if (count($stops) > $maxStops) {
            throw RoutingException::tooManyPoints(count($stops), $maxStops);
        }

        $all = array_merge([$warehouse], $stops);
        $coords = array_map(fn (RouteStop $s) => $s->coordinate, $all);
        $matrix = $this->provider->matrix($coords);

        $n = count($all);
        $visited = array_fill(0, $n, false);
        $visited[0] = true;

        $sequence = [];
        $current = 0;
        $cumulativeDistance = 0;
        $totalDuration = 0;
        $deliveryPos = 1;

        while (count($sequence) < $n - 1) {
            $next = $this->findNearestUnvisited($matrix, $current, $visited);

            if ($next === null) {
                break;
            }

            $distance = $matrix->distance($current, $next);
            $duration = $matrix->duration($current, $next);
            $cumulativeDistance += $distance;
            $totalDuration += $duration;

            $sequence[] = [
                'delivery_position' => $deliveryPos,
                'loading_position' => 0,
                'distance_from_prev_m' => $distance,
                'duration_from_prev_s' => $duration,
                'cumulative_distance_m' => $cumulativeDistance,
                'payload' => $all[$next]->payload,
            ];

            $visited[$next] = true;
            $current = $next;
            $deliveryPos++;
        }

        $totalStops = count($sequence);

        foreach ($sequence as $idx => &$step) {
            $step['loading_position'] = $totalStops - $idx;
        }
        unset($step);

        $returnDistance = $matrix->distance($current, 0);
        $returnDuration = $matrix->duration($current, 0);

        return new OptimizedRoute(
            sequence: $sequence,
            totalDistanceMeters: $cumulativeDistance,
            totalDurationSeconds: $totalDuration,
            returnToWarehouseDistanceMeters: $returnDistance,
            returnToWarehouseDurationSeconds: $returnDuration,
        );
    }

    /**
     * @param  array<int, bool>  $visited
     */
    private function findNearestUnvisited(DistanceMatrix $matrix, int $from, array $visited): ?int
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $size = $matrix->size();

        for ($i = 0; $i < $size; $i++) {
            if ($visited[$i]) {
                continue;
            }

            $d = $matrix->distance($from, $i);

            if ($d > 0 && $d < $bestDistance) {
                $bestDistance = $d;
                $best = $i;
            }
        }

        if ($best !== null) {
            return $best;
        }

        // Fallback — agar masofa 0 ga teng bo'lsa (juda yaqin nuqtalar yoki
        // matrix hali to'liq emas) — birinchi tashrif buyurilmaganni olamiz.
        for ($i = 0; $i < $size; $i++) {
            if (! $visited[$i]) {
                return $i;
            }
        }

        return null;
    }
}
