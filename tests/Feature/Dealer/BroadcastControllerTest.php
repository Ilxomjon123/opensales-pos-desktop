<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\BroadcastAudienceType;
use App\Enums\UserRole;
use App\Jobs\SendBroadcastMessageJob;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class BroadcastControllerTest extends TestCase
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

    private function seedAudience(): void
    {
        // Qarzdor mijoz
        $debtor = Shop::factory()->for($this->dealer)->create(['is_active' => true, 'balance' => -5000]);
        ShopMember::factory()->for($debtor)->create(['is_active' => true]);

        // Saldosi musbat mijoz
        $payer = Shop::factory()->for($this->dealer)->create(['is_active' => true, 'balance' => 3000]);
        ShopMember::factory()->for($payer)->create(['is_active' => true]);
    }

    public function test_index_renders_with_options(): void
    {
        $this->seedAudience();

        $this->actingAs($this->user)
            ->get('/dealer/broadcasts')
            ->assertOk();
    }

    public function test_preview_counts_all_active(): void
    {
        $this->seedAudience();

        $this->actingAs($this->user)
            ->getJson('/dealer/broadcasts/preview?audience_type='.BroadcastAudienceType::ALL_ACTIVE->value)
            ->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_preview_counts_filtered_debtors(): void
    {
        $this->seedAudience();

        $url = '/dealer/broadcasts/preview?audience_type='.BroadcastAudienceType::FILTER->value
            .'&audience_config[debtors_only]=1';

        $this->actingAs($this->user)
            ->getJson($url)
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_store_dispatches_to_filtered_audience(): void
    {
        Queue::fake();
        $this->seedAudience();

        $this->actingAs($this->user)
            ->post('/dealer/broadcasts', [
                'message' => 'Salom',
                'audience_type' => BroadcastAudienceType::FILTER->value,
                'audience_config' => ['debtors_only' => true],
            ])
            ->assertRedirect();

        Queue::assertPushed(SendBroadcastMessageJob::class, 1);
    }

    public function test_store_all_active_dispatches_each_member(): void
    {
        Queue::fake();
        $this->seedAudience();

        $this->actingAs($this->user)
            ->post('/dealer/broadcasts', [
                'message' => 'Hammaga',
                'audience_type' => BroadcastAudienceType::ALL_ACTIVE->value,
                'audience_config' => [],
            ])
            ->assertRedirect();

        Queue::assertPushed(SendBroadcastMessageJob::class, 2);
    }

    public function test_store_passes_buttons_and_shop_id_to_job(): void
    {
        Queue::fake();
        $this->seedAudience();

        $this->actingAs($this->user)
            ->post('/dealer/broadcasts', [
                'message' => 'Hurmatli {member_name}',
                'audience_type' => BroadcastAudienceType::ALL_ACTIVE->value,
                'audience_config' => [],
                'buttons' => [
                    [['text' => 'Katalog', 'url' => 'https://example.com']],
                ],
            ])
            ->assertRedirect();

        Queue::assertPushed(SendBroadcastMessageJob::class, function (SendBroadcastMessageJob $job): bool {
            return $job->shopId !== null
                && $job->buttons === [[['text' => 'Katalog', 'url' => 'https://example.com']]];
        });
    }

    public function test_store_rejects_invalid_button_url(): void
    {
        $this->seedAudience();

        $this->actingAs($this->user)
            ->post('/dealer/broadcasts', [
                'message' => 'X',
                'audience_type' => BroadcastAudienceType::ALL_ACTIVE->value,
                'audience_config' => [],
                'buttons' => [
                    [['text' => 'Bad', 'url' => 'not-a-url']],
                ],
            ])
            ->assertSessionHasErrors('buttons.0.0.url');
    }

    public function test_platform_audience_type_is_rejected_for_dealer(): void
    {
        $this->actingAs($this->user)
            ->post('/dealer/broadcasts', [
                'message' => 'X',
                'audience_type' => BroadcastAudienceType::PLATFORM_DEALERS->value,
                'audience_config' => [],
            ])
            ->assertSessionHasErrors('audience_type');
    }
}
