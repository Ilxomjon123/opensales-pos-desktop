<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BroadcastAudienceType;
use App\Jobs\SendBroadcastMessageJob;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PlatformBroadcastControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_index_renders(): void
    {
        $this->actingAs($this->admin)->get('/admin/broadcasts')->assertOk();
    }

    public function test_preview_counts_platform_dealers(): void
    {
        Dealer::factory()->create(['is_active' => true, 'telegram_chat_id' => 111]);
        Dealer::factory()->create(['is_active' => true, 'telegram_chat_id' => 222]);
        Dealer::factory()->create(['is_active' => true, 'telegram_chat_id' => null]);

        $this->actingAs($this->admin)
            ->getJson('/admin/broadcasts/preview?audience_type='.BroadcastAudienceType::PLATFORM_DEALERS->value)
            ->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_preview_filters_dealers_by_ids(): void
    {
        $a = Dealer::factory()->create(['is_active' => true, 'telegram_chat_id' => 111]);
        Dealer::factory()->create(['is_active' => true, 'telegram_chat_id' => 222]);

        $url = '/admin/broadcasts/preview?audience_type='.BroadcastAudienceType::PLATFORM_DEALERS->value
            ."&audience_config[dealer_ids][]={$a->id}";

        $this->actingAs($this->admin)
            ->getJson($url)
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_store_dispatches_to_shop_members(): void
    {
        Queue::fake();

        $dealer = Dealer::factory()->create(['is_active' => true]);
        $shop = Shop::factory()->for($dealer)->create(['is_active' => true]);
        ShopMember::factory()->for($shop)->count(2)->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->post('/admin/broadcasts', [
                'message' => 'Platformaga',
                'audience_type' => BroadcastAudienceType::PLATFORM_SHOP_MEMBERS->value,
                'audience_config' => [],
            ])
            ->assertRedirect();

        Queue::assertPushed(SendBroadcastMessageJob::class, 2);
    }
}
