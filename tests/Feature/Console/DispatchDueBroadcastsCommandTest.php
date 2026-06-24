<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastScheduleType;
use App\Jobs\RunBroadcastCampaignJob;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DispatchDueBroadcastsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_for_due_campaign(): void
    {
        Queue::fake();

        $dealer = Dealer::factory()->create();
        $user = User::factory()->create(['dealer_id' => $dealer->id]);

        $campaign = BroadcastCampaign::factory()
            ->for($dealer)
            ->create([
                'created_by_user_id' => $user->id,
                'audience_type' => BroadcastAudienceType::ALL_ACTIVE->value,
                'schedule_type' => BroadcastScheduleType::DAILY->value,
                'schedule_config' => ['time' => '09:00'],
                'is_active' => true,
                'next_run_at' => Carbon::now()->subMinute(),
            ]);

        $this->artisan('broadcasts:dispatch')->assertSuccessful();

        Queue::assertPushed(RunBroadcastCampaignJob::class, fn ($job) => $job->campaignId === $campaign->id);
    }

    public function test_skips_paused_campaign(): void
    {
        Queue::fake();

        $dealer = Dealer::factory()->create();
        $user = User::factory()->create(['dealer_id' => $dealer->id]);

        BroadcastCampaign::factory()
            ->for($dealer)
            ->create([
                'created_by_user_id' => $user->id,
                'is_active' => false,
                'next_run_at' => Carbon::now()->subMinute(),
            ]);

        $this->artisan('broadcasts:dispatch')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_claims_slot_so_campaign_is_not_redispatched_every_minute(): void
    {
        Queue::fake();

        $dealer = Dealer::factory()->create();
        $user = User::factory()->create(['dealer_id' => $dealer->id]);

        $campaign = BroadcastCampaign::factory()
            ->for($dealer)
            ->create([
                'created_by_user_id' => $user->id,
                'audience_type' => BroadcastAudienceType::ALL_ACTIVE->value,
                'schedule_type' => BroadcastScheduleType::DAILY->value,
                'schedule_config' => ['times' => ['08:00', '12:00', '18:00']],
                'is_active' => true,
                'next_run_at' => Carbon::now()->subMinute(),
            ]);

        // Birinchi tick — bir marta dispatch va next_run_at oldinga suriladi.
        $this->artisan('broadcasts:dispatch')->assertSuccessful();
        Queue::assertPushed(RunBroadcastCampaignJob::class, 1);

        $campaign->refresh();
        $this->assertNotNull($campaign->next_run_at);
        $this->assertTrue(
            $campaign->next_run_at->isFuture(),
            'next_run_at slot claim paytida kelajakka surilishi kerak'
        );

        // Ikkinchi tick (keyingi daqiqa) — endi due emas, qayta dispatch BO'LMAYDI.
        $this->artisan('broadcasts:dispatch')->assertSuccessful();
        Queue::assertPushed(RunBroadcastCampaignJob::class, 1);
    }

    public function test_skips_future_campaign(): void
    {
        Queue::fake();

        $dealer = Dealer::factory()->create();
        $user = User::factory()->create(['dealer_id' => $dealer->id]);

        BroadcastCampaign::factory()
            ->for($dealer)
            ->create([
                'created_by_user_id' => $user->id,
                'is_active' => true,
                'next_run_at' => Carbon::now()->addHour(),
            ]);

        $this->artisan('broadcasts:dispatch')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
