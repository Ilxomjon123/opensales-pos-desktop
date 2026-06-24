<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Events\LowStockDetected;
use App\Models\Dealer;
use App\Models\Product;
use App\Services\StockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class StockAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockAlertService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockAlertService::class);
    }

    public function test_dispatches_event_when_stock_is_low(): void
    {
        Event::fake([LowStockDetected::class]);

        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create([
            'stock' => 5,
            'min_stock' => 10,
        ]);

        $result = $this->service->checkAndNotify($product);

        $this->assertTrue($result);
        Event::assertDispatched(LowStockDetected::class);
    }

    public function test_returns_null_when_min_stock_not_set(): void
    {
        Event::fake([LowStockDetected::class]);

        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create([
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $this->assertNull($this->service->checkAndNotify($product));
        Event::assertNotDispatched(LowStockDetected::class);
    }

    public function test_returns_null_when_stock_above_threshold(): void
    {
        Event::fake([LowStockDetected::class]);

        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create([
            'stock' => 50,
            'min_stock' => 10,
        ]);

        $this->assertNull($this->service->checkAndNotify($product));
        Event::assertNotDispatched(LowStockDetected::class);
    }

    public function test_cooldown_prevents_duplicate_events(): void
    {
        Event::fake([LowStockDetected::class]);

        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create([
            'stock' => 5,
            'min_stock' => 10,
        ]);

        $this->service->checkAndNotify($product);
        $second = $this->service->checkAndNotify($product);

        $this->assertFalse($second);
        Event::assertDispatchedTimes(LowStockDetected::class, 1);
    }

    public function test_reset_cooldown_allows_new_event(): void
    {
        Event::fake([LowStockDetected::class]);

        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create([
            'stock' => 5,
            'min_stock' => 10,
        ]);

        $this->service->checkAndNotify($product);
        $this->service->resetCooldown($product->id);
        $second = $this->service->checkAndNotify($product);

        $this->assertTrue($second);
        Event::assertDispatchedTimes(LowStockDetected::class, 2);
    }
}
