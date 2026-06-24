<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Models\Dealer;
use App\Services\MarketplaceFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketplaceFinanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MarketplaceFinanceService
    {
        return app(MarketplaceFinanceService::class);
    }

    public function test_debit_makes_buyer_owe_seller_on_both_sides(): void
    {
        $seller = Dealer::factory()->create();
        $buyer = Dealer::factory()->create();

        $this->service()->debit($seller, $buyer, 500_000, note: 'order #1');

        // Sotuvchi nuqtai nazaridan: xaridor menga 500k qarzdor (musbat).
        $this->assertSame(500_000, $this->service()->balanceBetween($seller->id, $buyer->id));
        // Xaridor nuqtai nazaridan: men sotuvchiga 500k qarzdorman (manfiy).
        $this->assertSame(-500_000, $this->service()->balanceBetween($buyer->id, $seller->id));

        $this->assertDatabaseHas('marketplace_payments', [
            'seller_dealer_id' => $seller->id,
            'buyer_dealer_id' => $buyer->id,
            'amount' => 500_000,
            'type' => 'debit',
        ]);
    }

    public function test_credit_reduces_buyer_debt_on_both_sides(): void
    {
        $seller = Dealer::factory()->create();
        $buyer = Dealer::factory()->create();

        $this->service()->debit($seller, $buyer, 500_000);
        $this->service()->credit($seller, $buyer, 300_000, note: 'payment');

        $this->assertSame(200_000, $this->service()->balanceBetween($seller->id, $buyer->id));
        $this->assertSame(-200_000, $this->service()->balanceBetween($buyer->id, $seller->id));
    }

    public function test_rejects_same_dealer(): void
    {
        $dealer = Dealer::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->debit($dealer, $dealer, 1000);
    }

    public function test_rejects_non_positive_amount(): void
    {
        $seller = Dealer::factory()->create();
        $buyer = Dealer::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->debit($seller, $buyer, 0);
    }
}
