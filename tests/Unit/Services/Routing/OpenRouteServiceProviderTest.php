<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Routing;

use App\Exceptions\Domain\RoutingException;
use App\Services\Routing\Coordinate;
use App\Services\Routing\OpenRouteServiceProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Tests\TestCase;

final class OpenRouteServiceProviderTest extends TestCase
{
    public function test_builds_matrix_from_api_response(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*' => $http->response([
                'distances' => [
                    [0.0, 1500.5, 2300.0],
                    [1500.5, 0.0, 1800.0],
                    [2300.0, 1800.0, 0.0],
                ],
                'durations' => [
                    [0.0, 120.0, 180.0],
                    [120.0, 0.0, 150.0],
                    [180.0, 150.0, 0.0],
                ],
            ]),
        ]);

        $provider = new OpenRouteServiceProvider(
            http: $http,
            apiKey: 'test-key',
            endpoint: 'https://api.openrouteservice.org/v2/matrix',
            profile: 'driving-car',
        );

        $points = [
            new Coordinate(41.0, 69.0),
            new Coordinate(41.1, 69.1),
            new Coordinate(41.2, 69.2),
        ];

        $matrix = $provider->matrix($points);

        $this->assertSame(1501, $matrix->distance(0, 1));
        $this->assertSame(2300, $matrix->distance(0, 2));
        $this->assertSame(120, $matrix->duration(0, 1));
    }

    public function test_sends_locations_in_lng_lat_order(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*' => $http->response([
                'distances' => [[0.0, 100.0], [100.0, 0.0]],
                'durations' => [[0.0, 60.0], [60.0, 0.0]],
            ]),
        ]);

        $provider = new OpenRouteServiceProvider(
            http: $http,
            apiKey: 'test-key',
            endpoint: 'https://api.openrouteservice.org/v2/matrix',
            profile: 'driving-car',
        );

        $points = [
            new Coordinate(41.0, 69.0),
            new Coordinate(41.5, 69.5),
        ];

        $provider->matrix($points);

        $http->assertSent(function (Request $request): bool {
            $body = $request->data();

            return $body['locations'] === [[69.0, 41.0], [69.5, 41.5]]
                && in_array('distance', $body['metrics'], true)
                && in_array('duration', $body['metrics'], true);
        });
    }

    public function test_throws_when_api_key_missing(): void
    {
        $provider = new OpenRouteServiceProvider(
            http: new HttpFactory,
            apiKey: '',
            endpoint: 'https://api.openrouteservice.org/v2/matrix',
            profile: 'driving-car',
        );

        $this->expectException(RoutingException::class);

        $provider->matrix([new Coordinate(41.0, 69.0)]);
    }

    public function test_throws_when_too_many_locations(): void
    {
        $provider = new OpenRouteServiceProvider(
            http: new HttpFactory,
            apiKey: 'test-key',
            endpoint: 'https://api.openrouteservice.org/v2/matrix',
            profile: 'driving-car',
        );

        $points = array_fill(0, 51, new Coordinate(41.0, 69.0));

        $this->expectException(RoutingException::class);

        $provider->matrix($points);
    }

    public function test_throws_on_http_error(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*' => $http->response(['error' => 'forbidden'], 403),
        ]);

        $provider = new OpenRouteServiceProvider(
            http: $http,
            apiKey: 'invalid-key',
            endpoint: 'https://api.openrouteservice.org/v2/matrix',
            profile: 'driving-car',
        );

        $this->expectException(RoutingException::class);

        $provider->matrix([new Coordinate(41.0, 69.0), new Coordinate(41.1, 69.1)]);
    }
}
