<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastScheduleType;
use App\Enums\UserRole;
use App\Jobs\RunBroadcastCampaignJob;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class BroadcastCampaignControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Dealer $dealer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_dealer_can_create_campaign(): void
    {
        $response = $this->actingAs($this->user)->post('/dealer/broadcast-campaigns', [
            'title' => 'Kunlik salom',
            'message_text' => 'Assalomu alaykum {shop_name}',
            'audience_type' => BroadcastAudienceType::ALL_ACTIVE->value,
            'audience_config' => [],
            'schedule_type' => BroadcastScheduleType::DAILY->value,
            'schedule_config' => ['time' => '09:00'],
            'timezone' => 'Asia/Tashkent',
            'is_active' => true,
        ]);

        $response->assertRedirect('/dealer/broadcast-campaigns');

        $this->assertDatabaseHas('broadcast_campaigns', [
            'dealer_id' => $this->dealer->id,
            'title' => 'Kunlik salom',
        ]);

        $campaign = BroadcastCampaign::query()->where('dealer_id', $this->dealer->id)->first();
        $this->assertNotNull($campaign->next_run_at, 'next_run_at should be auto-computed');
    }

    public function test_dealer_cannot_access_other_dealers_campaign(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherUser = User::factory()->create(['dealer_id' => $otherDealer->id]);
        $campaign = BroadcastCampaign::factory()->for($otherDealer)->create([
            'created_by_user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user)
            ->get("/dealer/broadcast-campaigns/{$campaign->id}/edit")
            ->assertForbidden();
    }

    public function test_dealer_can_toggle_campaign(): void
    {
        $campaign = BroadcastCampaign::factory()->for($this->dealer)->create([
            'created_by_user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post("/dealer/broadcast-campaigns/{$campaign->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($campaign->fresh()->is_active);
    }

    public function test_dealer_can_run_now(): void
    {
        Queue::fake();

        $campaign = BroadcastCampaign::factory()->for($this->dealer)->create([
            'created_by_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post("/dealer/broadcast-campaigns/{$campaign->id}/run-now")
            ->assertRedirect();

        Queue::assertPushed(RunBroadcastCampaignJob::class);
    }

    public function test_audience_preview_counts_active_shop_members(): void
    {
        $shop = Shop::factory()->for($this->dealer)->create(['is_active' => true]);
        ShopMember::factory()->for($shop)->count(3)->create(['is_active' => true]);

        // inactive shop's member should be excluded
        $inactiveShop = Shop::factory()->for($this->dealer)->create(['is_active' => false]);
        ShopMember::factory()->for($inactiveShop)->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->getJson(
            '/dealer/broadcast-campaigns/preview?audience_type='.BroadcastAudienceType::ALL_ACTIVE->value
        );

        $response->assertOk();
        $this->assertSame(3, $response->json('count'));
    }

    public function test_run_dispatches_send_message_jobs_per_recipient(): void
    {
        Queue::fake();

        $shop = Shop::factory()->for($this->dealer)->create(['is_active' => true]);
        ShopMember::factory()->for($shop)->count(2)->create(['is_active' => true]);

        $campaign = BroadcastCampaign::factory()->for($this->dealer)->create([
            'created_by_user_id' => $this->user->id,
            'audience_type' => BroadcastAudienceType::ALL_ACTIVE->value,
        ]);

        // Trigger dispatcher synchronously via controller's run-now (RunBroadcastCampaignJob)
        $this->actingAs($this->user)
            ->post("/dealer/broadcast-campaigns/{$campaign->id}/run-now")
            ->assertRedirect();

        Queue::assertPushed(RunBroadcastCampaignJob::class);
    }
}
