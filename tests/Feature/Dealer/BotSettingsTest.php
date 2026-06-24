<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\BotVisibility;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BotSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dealer_can_toggle_notify_on_price_change(): void
    {
        $dealer = Dealer::factory()->create(['notify_on_price_change' => true]);
        $user = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $dealer->id]);

        $this->actingAs($user)
            ->from(route('dealer.bot.show'))
            ->put(route('dealer.bot.update'), ['notify_on_price_change' => false])
            ->assertRedirect(route('dealer.bot.show'));

        $this->assertFalse($dealer->fresh()->notify_on_price_change);
    }

    public function test_order_settings_form_persists_all_fields(): void
    {
        $dealer = Dealer::factory()->create([
            'visibility' => BotVisibility::PUBLIC,
            'min_order_amount' => 0,
            'show_out_of_stock' => true,
            'notify_on_price_change' => true,
            'notify_on_new_product' => true,
        ]);
        $user = User::factory()->create(['role' => UserRole::DEALER, 'dealer_id' => $dealer->id]);

        $this->actingAs($user)
            ->put(route('dealer.bot.update'), [
                'visibility' => 'private',
                'min_order_amount' => 5000,
                'show_out_of_stock' => false,
                'notify_on_price_change' => false,
                'notify_on_new_product' => false,
                'bot_display_name' => 'Toza Hayot',
            ])->assertRedirect();

        $d = $dealer->fresh();
        $this->assertSame('private', $d->visibility->value);
        $this->assertSame(5000, (int) $d->min_order_amount);
        $this->assertFalse($d->show_out_of_stock);
        $this->assertFalse($d->notify_on_price_change);
        $this->assertFalse($d->notify_on_new_product);
        $this->assertSame('Toza Hayot', $d->bot_display_name);
    }

    public function test_notify_on_price_change_defaults_to_true(): void
    {
        $dealer = Dealer::factory()->create();

        $this->assertTrue($dealer->fresh()->notify_on_price_change);
    }
}
