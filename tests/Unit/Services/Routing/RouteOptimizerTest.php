<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Routing;

use App\Exceptions\Domain\RoutingException;
use App\Services\Routing\Coordinate;
use App\Services\Routing\DistanceMatrix;
use App\Services\Routing\DistanceMatrixProvider;
use App\Services\Routing\RouteOptimizer;
use App\Services\Routing\RouteStop;
use Tests\TestCase;

final class RouteOptimizerTest extends TestCase
{
    public function test_throws_when_no_stops(): void
    {
        $optimizer = new RouteOptimizer($this->fakeProvider([]));
        $warehouse = new RouteStop(new Coordinate(0.0, 0.0), ['name' => 'ombor']);

        $this->expectException(RoutingException::class);

        $optimizer->optimize($warehouse, []);
    }

    public function test_throws_when_too_many_stops(): void
    {
        $optimizer = new RouteOptimizer($this->fakeProvider([]));
        $warehouse = new RouteStop(new Coordinate(0.0, 0.0), ['name' => 'ombor']);
        $stops = array_fill(0, 51, new RouteStop(new Coordinate(1.0, 1.0), ['order_id' => 1]));

        $this->expectException(RoutingException::class);

        $optimizer->optimize($warehouse, $stops, 50);
    }

    public function test_nearest_neighbor_picks_closest_unvisited(): void
    {
        // Warehouse (index 0) — 3 stops at distances 100, 50, 200 from warehouse.
        // From stop B (50m): A=200m, C=150m → C next, then A.
        // Expected delivery order: B → C → A.
        // Indekslar: 0=W, 1=A, 2=B, 3=C.
        // W: A=100, B=50, C=200 → eng yaqin B
        // B: A=200, C=60 → eng yaqin C
        // C: A=50 (yagona) → A
        // A → W = 100 (qaytish)
        // Yetkazish tartibi: W → B → C → A
        $matrix = [
            //  W    A    B    C
            [0, 100, 50, 200],
            [100, 0, 80, 50],
            [50, 200, 0, 60],
            [200, 50, 60, 0],
        ];

        $optimizer = new RouteOptimizer($this->fakeProvider($matrix));
        $warehouse = new RouteStop(new Coordinate(0.0, 0.0), ['name' => 'ombor']);
        $stops = [
            new RouteStop(new Coordinate(1.0, 1.0), ['id' => 'A']),
            new RouteStop(new Coordinate(2.0, 2.0), ['id' => 'B']),
            new RouteStop(new Coordinate(3.0, 3.0), ['id' => 'C']),
        ];

        $route = $optimizer->optimize($warehouse, $stops);

        $this->assertCount(3, $route->sequence);
        $this->assertSame('B', $route->sequence[0]['payload']['id']);
        $this->assertSame('C', $route->sequence[1]['payload']['id']);
        $this->assertSame('A', $route->sequence[2]['payload']['id']);

        $this->assertSame(1, $route->sequence[0]['delivery_position']);
        $this->assertSame(2, $route->sequence[1]['delivery_position']);
        $this->assertSame(3, $route->sequence[2]['delivery_position']);
    }

    public function test_loading_order_is_reverse_of_delivery(): void
    {
        $matrix = [
            //  W    A    B    C
            [0, 100, 50, 200],
            [100, 0, 80, 50],
            [50, 200, 0, 60],
            [200, 50, 60, 0],
        ];

        $optimizer = new RouteOptimizer($this->fakeProvider($matrix));
        $warehouse = new RouteStop(new Coordinate(0.0, 0.0), ['name' => 'ombor']);
        $stops = [
            new RouteStop(new Coordinate(1.0, 1.0), ['id' => 'A']),
            new RouteStop(new Coordinate(2.0, 2.0), ['id' => 'B']),
            new RouteStop(new Coordinate(3.0, 3.0), ['id' => 'C']),
        ];

        $route = $optimizer->optimize($warehouse, $stops);
        $loading = $route->loadingOrder();

        $this->assertSame('A', $loading[0]['payload']['id'], 'Eng uzoq nuqta birinchi yuklanadi');
        $this->assertSame('C', $loading[1]['payload']['id']);
        $this->assertSame('B', $loading[2]['payload']['id'], 'Eng yaqin nuqta oxiri yuklanadi');

        $this->assertSame(1, $loading[0]['loading_position']);
        $this->assertSame(2, $loading[1]['loading_position']);
        $this->assertSame(3, $loading[2]['loading_position']);
    }

    public function test_cumulative_distance_and_return_distance(): void
    {
        $matrix = [
            //  W    A    B    C
            [0, 100, 50, 200],
            [100, 0, 80, 50],
            [50, 200, 0, 60],
            [200, 50, 60, 0],
        ];

        $optimizer = new RouteOptimizer($this->fakeProvider($matrix));
        $warehouse = new RouteStop(new Coordinate(0.0, 0.0), ['name' => 'ombor']);
        $stops = [
            new RouteStop(new Coordinate(1.0, 1.0), ['id' => 'A']),
            new RouteStop(new Coordinate(2.0, 2.0), ['id' => 'B']),
            new RouteStop(new Coordinate(3.0, 3.0), ['id' => 'C']),
        ];

        $route = $optimizer->optimize($warehouse, $stops);

        // W→B(50) → C(60) → A(50) = total 160
        $this->assertSame(50, $route->sequence[0]['distance_from_prev_m']);
        $this->assertSame(60, $route->sequence[1]['distance_from_prev_m']);
        $this->assertSame(50, $route->sequence[2]['distance_from_prev_m']);

        $this->assertSame(50, $route->sequence[0]['cumulative_distance_m']);
        $this->assertSame(110, $route->sequence[1]['cumulative_distance_m']);
        $this->assertSame(160, $route->sequence[2]['cumulative_distance_m']);

        $this->assertSame(160, $route->totalDistanceMeters);
        // A → warehouse = 100
        $this->assertSame(100, $route->returnToWarehouseDistanceMeters);
    }

    /**
     * @param  array<int, array<int, int>>  $distanceMatrix
     */
    private function fakeProvider(array $distanceMatrix): DistanceMatrixProvider
    {
        return new class($distanceMatrix) implements DistanceMatrixProvider
        {
            /** @param  array<int, array<int, int>>  $matrix */
            public function __construct(private readonly array $matrix) {}

            public function matrix(array $points): DistanceMatrix
            {
                // Duration = distance (sekundlarda) — soddalashtirilgan model.
                return new DistanceMatrix(
                    points: $points,
                    distanceMeters: $this->matrix,
                    durationSeconds: $this->matrix,
                );
            }
        };
    }
}
