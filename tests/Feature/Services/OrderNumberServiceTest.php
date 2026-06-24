<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Services\OrderNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrderNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderNumberService::class);
    }

    public function test_assigns_sequential_numbers_starting_from_one(): void
    {
        $dealer = Dealer::factory()->create();

        $this->assertSame(1, $this->service->nextFor($dealer->id));
        $this->assertSame(2, $this->service->nextFor($dealer->id));
        $this->assertSame(3, $this->service->nextFor($dealer->id));
    }

    public function test_numbers_are_isolated_per_dealer(): void
    {
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();

        $this->assertSame(1, $this->service->nextFor($dealerA->id));
        $this->assertSame(1, $this->service->nextFor($dealerB->id));
        $this->assertSame(2, $this->service->nextFor($dealerA->id));
        $this->assertSame(2, $this->service->nextFor($dealerB->id));
        $this->assertSame(3, $this->service->nextFor($dealerB->id));
    }

    public function test_counter_row_is_persisted_with_last_number(): void
    {
        $dealer = Dealer::factory()->create();

        $this->service->nextFor($dealer->id);
        $this->service->nextFor($dealer->id);
        $this->service->nextFor($dealer->id);

        $row = DB::table('dealer_order_counters')->where('dealer_id', $dealer->id)->first();

        $this->assertNotNull($row);
        $this->assertSame(3, (int) $row->last_number);
    }

    public function test_observer_auto_assigns_number_when_order_created(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        $first = Order::factory()->create(['shop_id' => $shop->id, 'dealer_id' => $dealer->id]);
        $second = Order::factory()->create(['shop_id' => $shop->id, 'dealer_id' => $dealer->id]);

        $this->assertSame(1, $first->number);
        $this->assertSame(2, $second->number);
    }

    public function test_observer_respects_explicit_number_if_provided(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'dealer_id' => $dealer->id,
            'number' => 999,
        ]);

        $this->assertSame(999, $order->number);
    }

    public function test_monthly_numbers_reset_per_period(): void
    {
        $dealer = Dealer::factory()->create();

        $this->assertSame(1, $this->service->nextMonthlyFor($dealer->id, '2026-05'));
        $this->assertSame(2, $this->service->nextMonthlyFor($dealer->id, '2026-05'));
        // Yangi oy — qaytadan 1 dan.
        $this->assertSame(1, $this->service->nextMonthlyFor($dealer->id, '2026-06'));
        $this->assertSame(2, $this->service->nextMonthlyFor($dealer->id, '2026-06'));
        $this->assertSame(3, $this->service->nextMonthlyFor($dealer->id, '2026-05'));
    }

    public function test_observer_assigns_global_and_monthly_number(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        $first = Order::factory()->create(['shop_id' => $shop->id, 'dealer_id' => $dealer->id]);
        $second = Order::factory()->create(['shop_id' => $shop->id, 'dealer_id' => $dealer->id]);

        $this->assertSame(1, $first->month_number);
        $this->assertSame(2, $second->month_number);
        $this->assertSame(1, $first->displayNumber());
        $this->assertSame(2, $second->displayNumber());
    }

    public function test_numbers_remain_unique_per_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();

        for ($i = 0; $i < 5; $i++) {
            Order::factory()->create(['shop_id' => $shop->id, 'dealer_id' => $dealer->id]);
        }

        $numbers = Order::query()->where('dealer_id', $dealer->id)->pluck('number')->all();

        $this->assertSame([1, 2, 3, 4, 5], $numbers);
        $this->assertSame(count($numbers), count(array_unique($numbers)));
    }
}
