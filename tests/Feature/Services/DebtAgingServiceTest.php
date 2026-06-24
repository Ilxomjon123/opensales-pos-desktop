<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\PaymentType;
use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\DebtAgingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DebtAgingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DebtAgingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DebtAgingService::class);
    }

    public function test_ignores_shops_with_non_negative_balance(): void
    {
        $dealer = Dealer::factory()->create();
        Shop::factory()->for($dealer)->create(['balance' => 0]);
        Shop::factory()->for($dealer)->create(['balance' => 50_000]);

        $report = $this->service->report($dealer->id);

        $this->assertSame(0, $report['totals']['count']);
        $this->assertSame([], $report['rows']);
    }

    public function test_buckets_by_days_since_last_payment(): void
    {
        $dealer = Dealer::factory()->create();
        $shopRecent = Shop::factory()->for($dealer)->create(['balance' => -10_000]);
        $shopOld = Shop::factory()->for($dealer)->create(['balance' => -100_000]);

        Payment::factory()->for($dealer)->for($shopRecent)->create([
            'type' => PaymentType::CREDIT,
            'amount' => 500,
            'created_at' => CarbonImmutable::now()->subDays(10),
        ]);

        Payment::factory()->for($dealer)->for($shopOld)->create([
            'type' => PaymentType::CREDIT,
            'amount' => 500,
            'created_at' => CarbonImmutable::now()->subDays(120),
        ]);

        $report = $this->service->report($dealer->id);

        $this->assertSame(2, $report['totals']['count']);
        $this->assertSame(110_000, $report['totals']['debt']);
        $this->assertSame(1, $report['buckets']['current']['count']);
        $this->assertSame(1, $report['buckets']['critical']['count']);
    }
}
