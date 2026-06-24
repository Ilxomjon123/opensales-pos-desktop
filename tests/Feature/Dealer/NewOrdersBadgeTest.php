<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Models\Dealer;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class NewOrdersBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliveryman_badge_counts_assigned_and_unassigned_pending_orders(): void
    {
        $dealer = Dealer::factory()->create();
        $deliveryman = User::factory()->deliveryman($dealer->id)->create();
        $other = User::factory()->deliveryman($dealer->id)->create();
        $shop = Shop::factory()->for($dealer)->create();

        // O'ziga biriktirilgan
        Order::factory()->for($shop)->create([
            'dealer_id' => $dealer->id,
            'deliveryman_id' => $deliveryman->id,
        ]);
        // Hech kimga biriktirilmagan
        Order::factory()->for($shop)->create([
            'dealer_id' => $dealer->id,
            'deliveryman_id' => null,
        ]);
        // Boshqa kuryerga biriktirilgan — sanalmasligi kerak
        Order::factory()->for($shop)->create([
            'dealer_id' => $dealer->id,
            'deliveryman_id' => $other->id,
        ]);
        // Boshqa holatdagi (DELIVERED) biriktirilmagan — sanalmasligi kerak
        Order::factory()->delivered()->for($shop)->create([
            'dealer_id' => $dealer->id,
            'deliveryman_id' => null,
        ]);

        $this->actingAs($deliveryman)
            ->get('/dealer/routes/today')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('badges.new_orders', 2));
    }
}
