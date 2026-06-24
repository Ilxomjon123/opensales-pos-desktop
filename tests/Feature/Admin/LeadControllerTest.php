<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeadControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_guest_cannot_access_leads(): void
    {
        $this->get(route('admin.leads.index'))
            ->assertRedirect(route('login'));
    }

    public function test_dealer_user_cannot_access_leads(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $dealer->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.leads.index'))
            ->assertRedirect(route('dealer.stats.index'));
    }

    public function test_super_admin_can_list_leads(): void
    {
        Lead::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->has('leads.data', 3)
                ->where('totals.all', 3)
                ->where('totals.new', 3)
            );
    }

    public function test_filter_by_status(): void
    {
        Lead::factory()->status(LeadStatus::NEW)->count(2)->create();
        Lead::factory()->status(LeadStatus::CONVERTED)->count(1)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.leads.index', ['status' => 'converted']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('leads.data', 1));
    }

    public function test_super_admin_can_update_status(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.leads.update', $lead), ['status' => 'contacted'])
            ->assertRedirect();

        $this->assertSame(LeadStatus::CONTACTED, $lead->fresh()->status);
    }

    public function test_invalid_status_rejected(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.leads.update', $lead), ['status' => 'bogus'])
            ->assertSessionHasErrors(['status']);
    }

    public function test_super_admin_can_delete_lead(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.leads.destroy', $lead))
            ->assertRedirect();

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }
}
