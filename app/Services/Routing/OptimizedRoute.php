<?php

declare(strict_types=1);

namespace App\Services\Routing;

/**
 * TSP natijasi: ombor → o1 → o2 → ... → oN tartibi.
 *
 * - `sequence` — yetkazib berish tartibida (yaqindan uzoqqa).
 * - `loadingOrder` — mashinaga yuklash tartibi (LIFO: oxirgi yetkaziladigani
 *   birinchi yuklanadi). Bu — `sequence` ning teskarisi.
 *
 * Har bir element:
 *  - delivery_position: yetkazib berish tartib raqami (1, 2, ...)
 *  - loading_position: yuklash tartib raqami (1 = birinchi yuklanadi)
 *  - distance_from_prev_m: oldingi nuqtadan masofa (metr)
 *  - duration_from_prev_s: oldingi nuqtadan vaqt (sekund)
 *  - cumulative_distance_m: omborga qaytishgacha bo'lgan jami masofa
 *  - payload: original RouteStop payload (buyurtma ma'lumoti)
 */
final readonly class OptimizedRoute
{
    /**
     * @param  list<array{
     *     delivery_position: int,
     *     loading_position: int,
     *     distance_from_prev_m: int,
     *     duration_from_prev_s: int,
     *     cumulative_distance_m: int,
     *     payload: mixed,
     * }>  $sequence
     */
    public function __construct(
        public array $sequence,
        public int $totalDistanceMeters,
        public int $totalDurationSeconds,
        public int $returnToWarehouseDistanceMeters,
        public int $returnToWarehouseDurationSeconds,
    ) {}

    /**
     * Yuklash tartibida qaytaradi: oxiri yetkaziladigan birinchi.
     *
     * @return list<array{
     *     delivery_position: int,
     *     loading_position: int,
     *     distance_from_prev_m: int,
     *     duration_from_prev_s: int,
     *     cumulative_distance_m: int,
     *     payload: mixed,
     * }>
     */
    public function loadingOrder(): array
    {
        return array_reverse($this->sequence);
    }
}
