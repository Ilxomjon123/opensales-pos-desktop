<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\SendProductNotificationJob;
use App\Models\Dealer;
use App\Telegram\BotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

final class SendProductNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_text_message_without_photo(): void
    {
        $dealer = Dealer::factory()->create();

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')->once()->andReturnTrue();
        $bot->shouldReceive('sendPhoto')->never();
        $factory = $this->fakeBotFactory($bot);

        $job = new SendProductNotificationJob(
            dealerId: (int) $dealer->id,
            chatId: 100,
            productId: 5,
            text: 'Salom',
        );

        $job->handle($factory);
    }

    public function test_returns_silently_when_dealer_missing(): void
    {
        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('sendMessage')->never();
        $factory = $this->fakeBotFactory($bot);

        $job = new SendProductNotificationJob(
            dealerId: 999_999,
            chatId: 100,
            productId: 5,
            text: 'Salom',
        );

        $job->handle($factory);
    }

    private function fakeBotFactory(Nutgram $bot): BotFactory
    {
        return new class($bot) extends BotFactory
        {
            public function __construct(private readonly Nutgram $bot) {}

            public function make(string $token): Nutgram
            {
                return $this->bot;
            }
        };
    }
}
