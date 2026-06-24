<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Models\RoadDistance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * DB-backed kesh. Yandex'dan olingan har bir koordinata-juftlik DB'ga
 * yoziladi va keyingi safar API chaqiruvisiz qaytariladi.
 *
 * Yig'iladigan ma'lumotlar: loyiha vaqt o'tishi bilan o'z masofa bazasini
 * quradi, Yandex'ga ehtiyoj keskin kamayadi.
 *
 * Strategiya:
 *  1. So'ralgan barcha N×N juftliklarni DB'dan o'qiymiz.
 *  2. Hammasi mavjud va fresh (TTL ichida) → matritsa to'liq DB'dan, Yandex'siz.
 *  3. Bittasi yo'q yoki eskirgan → Yandex'dan to'liq matritsa olamiz va
 *     barcha juftliklarni DB'ga `upsert` qilamiz.
 */
final class PersistentDistanceMatrixProvider implements DistanceMatrixProvider
{
    private const int FRESHNESS_DAYS = 30;

    public function __construct(
        private readonly DistanceMatrixProvider $inner,
        private readonly string $mode,
    ) {}

    public function matrix(array $points): DistanceMatrix
    {
        $n = count($points);

        if ($n === 0) {
            return new DistanceMatrix($points, [], []);
        }

        $cached = $this->loadFromDb($points);

        if ($cached !== null) {
            $this->bumpFetchCounts($points);

            return $cached;
        }

        $matrix = $this->inner->matrix($points);
        $this->persist($matrix);

        return $matrix;
    }

    /**
     * @param  list<Coordinate>  $points
     */
    private function loadFromDb(array $points): ?DistanceMatrix
    {
        $n = count($points);
        $threshold = CarbonImmutable::now()->subDays(self::FRESHNESS_DAYS);

        $uniqueCoords = $this->uniqueCoordinates($points);

        if ($uniqueCoords === []) {
            return null;
        }

        $query = RoadDistance::query()
            ->where('mode', $this->mode)
            ->where('last_fetched_at', '>=', $threshold);

        $query->where(function ($q) use ($uniqueCoords): void {
            foreach ($uniqueCoords as $coord) {
                $q->orWhere(function ($qq) use ($coord): void {
                    $qq->where('origin_latitude', $coord->latitude)
                        ->where('origin_longitude', $coord->longitude);
                });
            }
        });

        $rows = $query->get();

        $lookup = [];

        foreach ($rows as $row) {
            $key = $this->pairKey($row->origin_latitude, $row->origin_longitude, $row->dest_latitude, $row->dest_longitude);
            $lookup[$key] = [
                'distance' => (int) $row->distance_meters,
                'duration' => (int) $row->duration_seconds,
            ];
        }

        $distance = array_fill(0, $n, array_fill(0, $n, 0));
        $duration = array_fill(0, $n, array_fill(0, $n, 0));

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    continue;
                }

                $key = $this->pairKey(
                    $points[$i]->latitude,
                    $points[$i]->longitude,
                    $points[$j]->latitude,
                    $points[$j]->longitude,
                );

                if (! isset($lookup[$key])) {
                    return null;
                }

                $distance[$i][$j] = $lookup[$key]['distance'];
                $duration[$i][$j] = $lookup[$key]['duration'];
            }
        }

        return new DistanceMatrix($points, $distance, $duration);
    }

    private function persist(DistanceMatrix $matrix): void
    {
        $now = CarbonImmutable::now();
        $points = $matrix->points;
        $n = count($points);

        // Dedupe: bir batch ichida bir xil (origin, dest, mode) jufti
        // PostgreSQL ON CONFLICT'ga xato beradi. Bir xil koordinatali
        // nuqtalar (masalan, ikki order bitta shop'dan) takrorlanadi.
        $unique = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    continue;
                }

                $oLat = round($points[$i]->latitude, 7);
                $oLng = round($points[$i]->longitude, 7);
                $dLat = round($points[$j]->latitude, 7);
                $dLng = round($points[$j]->longitude, 7);

                // Bir xil koordinatali nuqtalar (turli indekslar) — masofa
                // 0 ga teng, saqlashga ehtiyoj yo'q.
                if ($oLat === $dLat && $oLng === $dLng) {
                    continue;
                }

                $key = "{$oLat},{$oLng}|{$dLat},{$dLng}";

                if (isset($unique[$key])) {
                    continue;
                }

                $unique[$key] = [
                    'origin_latitude' => $oLat,
                    'origin_longitude' => $oLng,
                    'dest_latitude' => $dLat,
                    'dest_longitude' => $dLng,
                    'mode' => $this->mode,
                    'distance_meters' => $matrix->distance($i, $j),
                    'duration_seconds' => $matrix->duration($i, $j),
                    'fetch_count' => 1,
                    'last_fetched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($unique === []) {
            return;
        }

        foreach (array_chunk(array_values($unique), 500) as $chunk) {
            DB::table('road_distances')->upsert(
                $chunk,
                ['origin_latitude', 'origin_longitude', 'dest_latitude', 'dest_longitude', 'mode'],
                ['distance_meters', 'duration_seconds', 'last_fetched_at', 'updated_at'],
            );
        }
    }

    /**
     * @param  list<Coordinate>  $points
     */
    private function bumpFetchCounts(array $points): void
    {
        $uniqueCoords = $this->uniqueCoordinates($points);

        if ($uniqueCoords === []) {
            return;
        }

        $query = RoadDistance::query()->where('mode', $this->mode);

        $query->where(function ($q) use ($uniqueCoords): void {
            foreach ($uniqueCoords as $coord) {
                $q->orWhere(function ($qq) use ($coord): void {
                    $qq->where('origin_latitude', $coord->latitude)
                        ->where('origin_longitude', $coord->longitude);
                });
            }
        });

        $query->increment('fetch_count');
    }

    /**
     * @param  list<Coordinate>  $points
     * @return list<Coordinate>
     */
    private function uniqueCoordinates(array $points): array
    {
        $seen = [];
        $result = [];

        foreach ($points as $p) {
            $key = $p->toString();

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $p;
            }
        }

        return $result;
    }

    private function pairKey(float $oLat, float $oLng, float $dLat, float $dLng): string
    {
        return sprintf('%.7f,%.7f|%.7f,%.7f', $oLat, $oLng, $dLat, $dLng);
    }
}
