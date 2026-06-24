<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Models\Dealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BotControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_dealer_webhook_returns_200(): void
    {
        $dealer = Dealer::factory()->create(['is_active' => true]);

        $this->postJson(route('telegram.webhook', $dealer))
            ->assertOk();
    }

    public function test_inactive_dealer_returns_404(): void
    {
        $dealer = Dealer::factory()->create(['is_active' => false]);

        $this->postJson(route('telegram.webhook', $dealer))
            ->assertNotFound();
    }

    public function test_nonexistent_dealer_returns_404(): void
    {
        $this->postJson('/webhook/99999')
            ->assertNotFound();
    }

    public function test_webhook_with_update_payload_returns_200(): void
    {
        $dealer = Dealer::factory()->create(['is_active' => true]);

        // Telegram Update formatida payload yuboramiz
        $payload = [
            'update_id' => 123456,
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => 99999,
                    'is_bot' => false,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 99999,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/start',
            ],
        ];

        // Xato bo'lsa ham 200 qaytishi kerak (Telegram retry qilmasligi uchun)
        $this->postJson(route('telegram.webhook', $dealer), $payload)
            ->assertOk();
    }
}
