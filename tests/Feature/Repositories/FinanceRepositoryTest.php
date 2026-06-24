<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Enums\OrderStatus;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Repositories\FinanceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FinanceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private FinanceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(FinanceRepository::class);
    }

    public function test_shop_pending_total_sums_only_open_orders(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::PENDING, 'total' => 100_000]);
        Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::ASSEMBLING, 'total' => 50_000]);
        Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::DELIVERING, 'total' => 30_000]);
        // Quyidagilar hisobga olinmaydi:
        Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::DELIVERED, 'total' => 200_000]);
        Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::RECEIVED, 'total' => 300_000]);
        Order::factory()->for($dealer)->for($shop)->create(['status' => OrderStatus::CANCELLED, 'total' => 70_000]);

        $this->assertSame(180_000, $this->repo->shopPendingTotal($shop->id));
    }

    public function test_pending_totals_by_shop_groups_per_shop(): void
    {
        $dealer = Dealer::factory()->create();
        $a = Shop::factory()->for($dealer)->create();
        $b = Shop::factory()->for($dealer)->create();
        $other = Shop::factory()->for(Dealer::factory()->create())->create();

        Order::factory()->for($dealer)->for($a)->create(['status' => OrderStatus::PENDING, 'total' => 100_000]);
        Order::factory()->for($dealer)->for($a)->create(['status' => OrderStatus::ASSEMBLING, 'total' => 25_000]);
        Order::factory()->for($dealer)->for($b)->create(['status' => OrderStatus::DELIVERING, 'total' => 40_000]);
        Order::factory()->for($dealer)->for($b)->create(['status' => OrderStatus::DELIVERED, 'total' => 999_999]);
        // Boshqa diller — chiqmasligi kerak
        Order::factory()->for($other->dealer)->for($other)->create(['status' => OrderStatus::PENDING, 'total' => 12_345]);

        $totals = $this->repo->pendingTotalsByShop($dealer->id);

        $this->assertCount(2, $totals);
        $this->assertSame(125_000, $totals[$a->id]);
        $this->assertSame(40_000, $totals[$b->id]);
        $this->assertArrayNotHasKey($other->id, $totals);
    }
}
