<?php

declare(strict_types=1);

namespace Tests\Feature\MiniApp;

use App\Models\Dealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InfoEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_info_endpoint_returns_project_and_dealer_payload(): void
    {
        config()->set('project.name', 'OpenSales');
        config()->set('project.url', 'https://opensales.uz');
        config()->set('project.support_telegram', 'opensales_support');
        config()->set('project.version', '2.4.1');

        $dealer = Dealer::factory()->create([
            'name' => 'Acme',
            'bot_username' => 'acme_bot',
            'contact_phone' => '+998 90 123 45 67',
        ]);

        $response = $this->getJson("/api/miniapp/{$dealer->id}/info?dev_telegram_id=12345");

        $response->assertOk()
            ->assertJsonPath('project.name', 'OpenSales')
            ->assertJsonPath('project.url', 'https://opensales.uz')
            ->assertJsonPath('project.support_telegram', 'opensales_support')
            ->assertJsonPath('project.version', '2.4.1')
            ->assertJsonPath('dealer.id', $dealer->id)
            ->assertJsonPath('dealer.bot_username', 'acme_bot')
            ->assertJsonPath('dealer.contact_phone', '+998 90 123 45 67')
            ->assertJsonStructure([
                'dealer' => [
                    'id',
                    'name',
                    'bot_username',
                    'bot_display_name',
                    'bot_short_description',
                    'bot_description',
                    'contact_phone',
                ],
                'project' => [
                    'name',
                    'url',
                    'support_telegram',
                    'version',
                ],
            ]);
    }
}
