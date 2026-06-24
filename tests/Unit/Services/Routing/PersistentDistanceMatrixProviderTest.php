<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Routing;

use App\Models\RoadDistance;
use App\Services\Routing\Coordinate;
use App\Services\Routing\DistanceMatrix;
use App\Services\Routing\DistanceMatrixProvider;
use App\Services\Routing\PersistentDistanceMatrixProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PersistentDistanceMatrixProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_from_inner_when_db_empty_and_persists_all_pairs(): void
    {
        $innerCallCount = 0;
        $inner = $this->fakeInner([
            [0, 100, 200],
            [100, 0, 150],
            [200, 150, 0],
        ], $innerCallCount);

        $provider = new PersistentDistanceMatrixProvider($inner, 'driving');

        $points = [
            new Coordinate(41.0, 69.0),
            new Coordinate(41.1, 69.1),
            new Coordinate(41.2, 69.2),
        ];

        $matrix = $provider->matrix($points);

        $this->assertSame(1, $innerCallCount);
        $this->assertSame(100, $matrix->distance(0, 1));

        // Barcha juftliklar DB'ga yozildi (3×3 - diagonal = 6 ta).
        $this->assertSame(6, RoadDistance::query()->where('mode', 'driving')->count());
    }

    public function test_reuses_db_cache_without_calling_inner(): void
    {
        $innerCallCount = 0;
        $inner = $this->fakeInner([
            [0, 100, 200],
            [100, 0, 150],
            [200, 150, 0],
        ], $innerCallCount);

        $provider = new PersistentDistanceMatrixProvider($inner, 'driving');

        $points = [
            new Coordinate(41.0, 69.0),
            new Coordinate(41.1, 69.1),
            new Coordinate(41.2, 69.2),
        ];

        $provider->matrix($points);
        $this->assertSame(1, $innerCallCount);

        // Ikkinchi safar — DB'dan, API'ga murojaat yo'q.
        $matrix = $provider->matrix($points);
        $this->assertSame(1, $innerCallCount, 'Cache hit — inner provider chaqirilmasligi kerak');
        $this->assertSame(100, $matrix->distance(0, 1));
        $this->assertSame(150, $matrix->distance(1, 2));
    }

    public function test_increments_fetch_count_on_cache_hit(): void
    {
        $innerCallCount = 0;
        $inner = $this->fakeInner([
            [0, 100],
            [100, 0],
        ], $innerCallCount);

        $provider = new PersistentDistanceMatrixProvider($inner, 'driving');

        $points = [
            new Coordinate(41.0, 69.0),
            new Coordinate(41.1, 69.1),
        ];

        $provider->matrix($points);

        $initialCount = (int) RoadDistance::query()->where('mode', 'driving')->first()->fetch_count;

        $provider->matrix($points);

        $afterCount = (int) RoadDistance::query()->where('mode', 'driving')->first()->fetch_count;

        $this->assertGreaterThan($initialCount, $afterCount);
    }

    public function test_persists_without_conflict_when_points_have_duplicate_coordinates(): void
    {
        // Ikki buyurtma bir xil do'kondan → bir xil koordinatali 2 ta nuqta.
        // ON CONFLICT'ga xato bermasligi kerak.
        $innerCallCount = 0;
        $inner = $this->fakeInner([
            [0, 100, 100],
            [100, 0, 0],
            [100, 0, 0],
        ], $innerCallCount);

        $provider = new PersistentDistanceMatrixProvider($inner, 'driving');

        $points = [
            new Coordinate(41.0, 69.0),
            new Coordinate(41.1, 69.1),
            new Coordinate(41.1, 69.1),
        ];

        $matrix = $provider->matrix($points);

        $this->assertSame(100, $matrix->distance(0, 1));

        // Unique juftliklar soni: (W↔A): 2 ta. Bir xil koordinatadan o'ziga
        // ham bitta yozuv. Jami: 2 (W→A, A→W).
        $this->assertSame(2, RoadDistance::query()->where('mode', 'driving')->count());
    }

    public function test_refetches_when_one_pair_missing_in_db(): void
    {
        $innerCallCount = 0;
        $inner = $this->fakeInner([
            [0, 100, 200],
            [100, 0, 150],
            [200, 150, 0],
        ], $innerCallCount);

        $provider = new PersistentDistanceMatrixProvider($inner, 'driving');

        $points = [
            new Coordinate(41.0, 69.0),
            new Coordinate(41.1, 69.1),
        ];

        // Faqat 2 ta nuqta uchun cache to'ldiramiz.
        $provider->matrix($points);
        $this->assertSame(1, $innerCallCount);

        // 3 ta nuqta bilan — yangi juftliklar yo'q, qayta fetch.
        $largerPoints = [
            new Coordinate(41.0, 69.0),
            new Coordinate(41.1, 69.1),
            new Coordinate(41.2, 69.2),
        ];
        $provider->matrix($largerPoints);
        $this->assertSame(2, $innerCallCount);
    }

    /**
     * @param  array<int, array<int, int>>  $matrix
     */
    private function fakeInner(array $matrix, int &$callCount): DistanceMatrixProvider
    {
        return new class($matrix, $callCount) implements DistanceMatrixProvider
        {
            /** @param  array<int, array<int, int>>  $matrix */
            public function __construct(private readonly array $matrix, private int &$callCount) {}

            public function matrix(array $points): DistanceMatrix
            {
                $this->callCount++;

                return new DistanceMatrix(
                    points: $points,
                    distanceMeters: $this->matrix,
                    durationSeconds: $this->matrix,
                );
            }
        };
    }
}
