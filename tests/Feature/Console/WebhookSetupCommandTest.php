<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\SetupWebhookJob;
use App\Models\Dealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WebhookSetupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_per_active_dealer(): void
    {
        Queue::fake();

        Dealer::factory()->count(3)->create();
        Dealer::factory()->inactive()->create();

        $this->artisan('webhook:setup')->assertSuccessful();

        Queue::assertPushed(SetupWebhookJob::class, 3);
        Queue::assertPushed(SetupWebhookJob::class, fn (SetupWebhookJob $job) => $job->remove === false);
    }

    public function test_dispatches_remove_job_for_single_dealer(): void
    {
        Queue::fake();

        $dealer = Dealer::factory()->create();
        Dealer::factory()->create();

        $this->artisan('webhook:setup', ['--dealer' => $dealer->id, '--remove' => true])
            ->assertSuccessful();

        Queue::assertPushed(SetupWebhookJob::class, 1);
        Queue::assertPushed(
            SetupWebhookJob::class,
            fn (SetupWebhookJob $job) => $job->dealerId === $dealer->id && $job->remove === true,
        );
    }

    public function test_sync_flag_skips_queue(): void
    {
        Queue::fake();

        Dealer::factory()->create();

        $this->artisan('webhook:setup', ['--sync' => true]);

        Queue::assertNothingPushed();
    }
}
