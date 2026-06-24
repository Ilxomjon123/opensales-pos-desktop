<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Contracts\WebhookServiceInterface;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

final class DealerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->superAdmin()->create();

        // WebhookService ni mock qilamiz — real Telegram API chaqirilmasin
        $this->mock(WebhookServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('register')->andReturn(true);
            $mock->shouldReceive('remove')->andReturn(true);
            $mock->shouldReceive('verifyToken')->andReturn('test_bot');
            $mock->shouldReceive('url')->andReturn('https://example.com/webhook/1');
        });
    }

    public function test_guest_cannot_access_dealers(): void
    {
        $this->get(route('admin.dealers.index'))->assertRedirect(route('login'));
    }

    public function test_dealer_user_cannot_access_admin(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.dealers.index'))
            ->assertRedirect(route('dealer.stats.index'));
    }

    public function test_super_admin_can_list_dealers(): void
    {
        Dealer::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.dealers.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_dealer(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dealers.create'))
            ->assertOk();
    }

    public function test_super_admin_can_store_dealer(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.dealers.store'), [
                'name' => 'Test Dealer',
                'username' => 'test_dealer',
                'password' => 'secret123',
                'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUV',
                'bot_username' => 'test_dealer_bot',
            ])
            ->assertRedirect(route('admin.dealers.index'));

        $this->assertDatabaseHas('dealers', ['name' => 'Test Dealer']);
        $this->assertDatabaseHas('users', [
            'username' => 'test_dealer',
            'role' => 'dealer',
        ]);
    }

    public function test_store_without_username_generates_placeholder(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.dealers.store'), [
                'name' => 'Auto Username Dealer',
                'username' => 'auto_dealer',
                'password' => 'secret123',
                'bot_token' => '987654321:XYZabcDEFghiJKLmnoPQR',
            ])
            ->assertRedirect(route('admin.dealers.index'));

        $dealer = Dealer::query()->where('name', 'Auto Username Dealer')->first();
        $this->assertNotNull($dealer);
        $this->assertStringStartsWith('bot_', $dealer->bot_username);
    }

    public function test_store_validates_token_format(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.dealers.store'), [
                'name' => 'Bad Token',
                'bot_token' => 'invalid-token-format',
            ])
            ->assertSessionHasErrors('bot_token');
    }

    public function test_store_rejects_duplicate_token(): void
    {
        Dealer::factory()->create(['bot_token' => '111111111:AAAAAAAAAAAAA']);

        $this->actingAs($this->admin)
            ->post(route('admin.dealers.store'), [
                'name' => 'Duplicate',
                'bot_token' => '111111111:AAAAAAAAAAAAA',
            ])
            ->assertSessionHasErrors('bot_token');
    }

    public function test_super_admin_can_update_dealer(): void
    {
        $dealer = Dealer::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin)
            ->put(route('admin.dealers.update', $dealer), [
                'name' => 'New Name',
            ])
            ->assertRedirect(route('admin.dealers.index'));

        $this->assertSame('New Name', $dealer->fresh()->name);
    }

    public function test_update_changes_owner_credentials_not_staff(): void
    {
        $dealer = Dealer::factory()->create();
        $owner = User::factory()->for($dealer)->create([
            'role' => UserRole::DEALER,
            'username' => 'old_owner',
        ]);
        // Xodimlar (owner emas) — login ma'lumotlari ularga tegmasligi kerak.
        $warehouse = User::factory()->for($dealer)->create([
            'role' => UserRole::WAREHOUSE,
            'username' => 'warehouse_user',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.dealers.update', $dealer), [
                'username' => 'new_owner',
                'password' => 'newsecret123',
            ])
            ->assertRedirect(route('admin.dealers.index'));

        $owner->refresh();
        $this->assertSame('new_owner', $owner->username);
        $this->assertTrue(Hash::check('newsecret123', $owner->password));

        // Xodim o'zgarmagan.
        $this->assertSame('warehouse_user', $warehouse->fresh()->username);
    }

    public function test_update_with_new_token_re_registers_webhook(): void
    {
        $webhookMock = $this->mock(WebhookServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('register')->once()->andReturn(true);
            $mock->shouldReceive('remove')->andReturn(true);
        });

        $dealer = Dealer::factory()->create(['bot_token' => '111111111:OLDTOKEN']);

        $this->actingAs($this->admin)
            ->put(route('admin.dealers.update', $dealer), [
                'bot_token' => '222222222:NEWTOKEN-abcdef',
            ])
            ->assertRedirect(route('admin.dealers.index'));

        $this->assertSame('222222222:NEWTOKEN-abcdef', $dealer->fresh()->bot_token);
    }

    public function test_super_admin_can_delete_dealer(): void
    {
        $dealer = Dealer::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.dealers.destroy', $dealer))
            ->assertRedirect(route('admin.dealers.index'));

        $this->assertDatabaseMissing('dealers', ['id' => $dealer->id]);
    }

    public function test_super_admin_can_toggle_dealer_active(): void
    {
        $dealer = Dealer::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->patch(route('admin.dealers.toggle', $dealer))
            ->assertRedirect();

        $this->assertFalse($dealer->fresh()->is_active);

        $this->actingAs($this->admin)
            ->patch(route('admin.dealers.toggle', $dealer))
            ->assertRedirect();

        $this->assertTrue($dealer->fresh()->is_active);
    }
}
