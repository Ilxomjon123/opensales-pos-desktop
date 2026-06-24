<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Routing\DistanceMatrix;
use App\Services\Routing\DistanceMatrixProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoadingRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Dealer $dealer;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dealer = Dealer::factory()->create([
            'warehouse_latitude' => 41.3111,
            'warehouse_longitude' => 69.2797,
            'warehouse_address' => 'Tashkent ombor',
        ]);

        $this->owner = User::factory()->create([
            'dealer_id' => $this->dealer->id,
            'role' => UserRole::DEALER,
        ]);

        // Fake provider — har bir juftlik uchun 1000m masofa qaytaradi (juftlik
        // o'rta nuqtalari turlicha, lekin TSP testlari uchun ahamiyatsiz).
        $this->app->instance(DistanceMatrixProvider::class, new class implements DistanceMatrixProvider
        {
            public function matrix(array $points): DistanceMatrix
            {
                $n = count($points);
                $distance = array_fill(0, $n, array_fill(0, $n, 1000));

                for ($i = 0; $i < $n; $i++) {
                    $distance[$i][$i] = 0;
                }

                return new DistanceMatrix(
                    points: $points,
                    distanceMeters: $distance,
                    durationSeconds: $distance,
                );
            }
        });
    }

    public function test_returns_loading_and_delivery_sequences(): void
    {
        $shopA = Shop::factory()->for($this->dealer)->create([
            'name' => 'A', 'latitude' => 41.31, 'longitude' => 69.28,
        ]);
        $shopB = Shop::factory()->for($this->dealer)->create([
            'name' => 'B', 'latitude' => 41.32, 'longitude' => 69.29,
        ]);

        $orderA = Order::factory()->for($this->dealer)->for($shopA)->create([
            'status' => OrderStatus::PENDING,
        ]);
        $orderB = Order::factory()->for($this->dealer)->for($shopB)->create([
            'status' => OrderStatus::ASSEMBLING,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/dealer/orders/loading-route', [
                'order_ids' => [$orderA->id, $orderB->id],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'warehouse' => ['name', 'address', 'latitude', 'longitude'],
                'delivery_sequence' => [['delivery_position', 'loading_position', 'payload']],
                'loading_sequence' => [['delivery_position', 'loading_position', 'payload']],
                'total_distance_meters',
                'total_duration_seconds',
                'return_distance_meters',
                'return_duration_seconds',
                'skipped',
            ]);

        $data = $response->json();

        $this->assertCount(2, $data['delivery_sequence']);
        $this->assertCount(2, $data['loading_sequence']);

        // Loading order — delivery order'ning teskarisi.
        $deliveryIds = array_column(array_column($data['delivery_sequence'], 'payload'), 'order_id');
        $loadingIds = array_column(array_column($data['loading_sequence'], 'payload'), 'order_id');

        $this->assertSame($deliveryIds, array_reverse($loadingIds));
    }

    public function test_returns_422_when_warehouse_not_configured(): void
    {
        $this->dealer->update([
            'warehouse_latitude' => null,
            'warehouse_longitude' => null,
        ]);

        $shop = Shop::factory()->for($this->dealer)->create([
            'latitude' => 41.31, 'longitude' => 69.28,
        ]);
        $order = Order::factory()->for($this->dealer)->for($shop)->create([
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/dealer/orders/loading-route', [
                'order_ids' => [$order->id],
            ]);

        $response->assertStatus(422)
            ->assertJson(['code' => 'warehouse_not_configured']);
    }

    public function test_validates_order_ids_required(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/dealer/orders/loading-route', [
                'order_ids' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_rejects_more_than_max_stops(): void
    {
        $ids = range(1, 51);

        $response = $this->actingAs($this->owner)
            ->postJson('/dealer/orders/loading-route', [
                'order_ids' => $ids,
            ]);

        $response->assertStatus(422);
    }

    public function test_filters_out_non_deliverable_statuses(): void
    {
        $shopA = Shop::factory()->for($this->dealer)->create([
            'latitude' => 41.31, 'longitude' => 69.28,
        ]);
        $shopB = Shop::factory()->for($this->dealer)->create([
            'latitude' => 41.32, 'longitude' => 69.29,
        ]);

        $deliveredOrder = Order::factory()->for($this->dealer)->for($shopA)->create([
            'status' => OrderStatus::DELIVERED,
        ]);
        $pendingOrder = Order::factory()->for($this->dealer)->for($shopB)->create([
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/dealer/orders/loading-route', [
                'order_ids' => [$deliveredOrder->id, $pendingOrder->id],
            ]);

        $response->assertOk();

        $data = $response->json();
        $this->assertCount(1, $data['delivery_sequence']);
        $this->assertSame($pendingOrder->id, $data['delivery_sequence'][0]['payload']['order_id']);
    }

    public function test_rejects_orders_from_other_dealers(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherShop = Shop::factory()->for($otherDealer)->create([
            'latitude' => 41.31, 'longitude' => 69.28,
        ]);
        $foreignOrder = Order::factory()->for($otherDealer)->for($otherShop)->create([
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/dealer/orders/loading-route', [
                'order_ids' => [$foreignOrder->id],
            ]);

        $response->assertStatus(422);
    }
}
