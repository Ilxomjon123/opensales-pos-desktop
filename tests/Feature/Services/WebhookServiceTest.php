<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Dealer;
use App\Services\WebhookService;
use App\Telegram\BotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonDefault;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonWebApp;
use Tests\TestCase;

final class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokenless_dealer_returns_null_info_without_building_bot(): void
    {
        $dealer = Dealer::factory()->create(['bot_token' => null, 'bot_username' => null]);

        // BotFactory chaqirilmasligi kerak — null token bilan crash bo'lmasin.
        $factory = Mockery::mock(BotFactory::class);
        $factory->shouldNotReceive('make');
        $this->app->instance(BotFactory::class, $factory);

        $service = $this->app->make(WebhookService::class);

        $this->assertNull($service->getInfo($dealer));
        $this->assertFalse($service->register($dealer));
        $this->assertFalse($service->remove($dealer));
        $this->assertFalse($service->applyProfile($dealer));
    }

    public function test_register_sets_webapp_menu_button(): void
    {
        $dealer = Dealer::factory()->create();

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('setWebhook')->once();
        $bot->shouldReceive('getMe')->andReturn(null);
        $bot->shouldReceive('setChatMenuButton')
            ->once()
            ->withArgs(function (...$args) use ($dealer): bool {
                $button = $args[1] ?? null;
                if (! $button instanceof MenuButtonWebApp) {
                    return false;
                }

                $expectedUrl = route('miniapp', ['dealer' => $dealer->id]);

                return $button->text === 'Buyurtma berish'
                    && $button->web_app->url === $expectedUrl;
            })
            ->andReturnTrue();

        $this->bindBotFactory($bot);

        $ok = app(WebhookService::class)->register($dealer);

        $this->assertTrue($ok);
        $this->assertNotNull($dealer->fresh()->webhook_set_at);
    }

    public function test_remove_resets_menu_button_to_default(): void
    {
        $dealer = Dealer::factory()->create(['webhook_set_at' => now()]);

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('deleteWebhook')->once();
        $bot->shouldReceive('setChatMenuButton')
            ->once()
            ->withArgs(fn (...$args) => ($args[1] ?? null) instanceof MenuButtonDefault)
            ->andReturnTrue();

        $this->bindBotFactory($bot);

        $ok = app(WebhookService::class)->remove($dealer);

        $this->assertTrue($ok);
        $this->assertNull($dealer->fresh()->webhook_set_at);
    }

    public function test_set_menu_button_can_be_called_directly(): void
    {
        $dealer = Dealer::factory()->create();

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('setChatMenuButton')
            ->once()
            ->withArgs(fn (...$args) => ($args[1] ?? null) instanceof MenuButtonWebApp)
            ->andReturnTrue();

        $this->bindBotFactory($bot);

        $this->assertTrue(app(WebhookService::class)->setMenuButton($dealer));
    }

    public function test_register_returns_true_even_if_menu_button_call_fails(): void
    {
        $dealer = Dealer::factory()->create();

        $bot = Mockery::mock(Nutgram::class);
        $bot->shouldReceive('setWebhook')->once();
        $bot->shouldReceive('getMe')->andReturn(null);
        $bot->shouldReceive('setChatMenuButton')
            ->once()
            ->andThrow(new \RuntimeException('telegram down'));

        $this->bindBotFactory($bot);

        $this->assertTrue(app(WebhookService::class)->register($dealer));
    }

    private function bindBotFactory(Nutgram $bot): void
    {
        $this->app->bind(BotFactory::class, fn () => new class($bot) extends BotFactory
        {
            public function __construct(private readonly Nutgram $bot)
            {
                // Test override — Application kerak emas
            }

            public function make(string $token): Nutgram
            {
                return $this->bot;
            }
        });
    }
}
