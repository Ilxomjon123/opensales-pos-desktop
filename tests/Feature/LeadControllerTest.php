<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders(): void
    {
        $this->withoutVite()
            ->get(route('home'))
            ->assertOk();
    }

    public function test_guest_can_submit_lead(): void
    {
        $this->withoutVite()
            ->from(route('home'))
            ->post(route('leads.store'), [
                'name' => 'Bekzod Karimov',
                'phone' => '+998901234567',
                'company' => 'Karimov LLC',
                'message' => 'Demo so\'rayman',
            ])
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('leads', [
            'name' => 'Bekzod Karimov',
            'phone' => '+998901234567',
            'company' => 'Karimov LLC',
            'status' => LeadStatus::NEW->value,
        ]);
    }

    public function test_validation_rejects_missing_name(): void
    {
        $this->withoutVite()
            ->from(route('home'))
            ->post(route('leads.store'), [
                'phone' => '+998901234567',
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_validation_rejects_invalid_phone(): void
    {
        $this->withoutVite()
            ->from(route('home'))
            ->post(route('leads.store'), [
                'name' => 'Test',
                'phone' => 'abc-not-a-phone',
            ])
            ->assertSessionHasErrors(['phone']);
    }

    public function test_lead_records_ip_and_user_agent(): void
    {
        $this->withoutVite()
            ->withServerVariables([
                'HTTP_USER_AGENT' => 'Mozilla/5.0 TestAgent',
                'REMOTE_ADDR' => '203.0.113.42',
            ])
            ->post(route('leads.store'), [
                'name' => 'Olim',
                'phone' => '+998901112233',
            ]);

        $lead = Lead::query()->first();
        $this->assertNotNull($lead);
        $this->assertSame('203.0.113.42', $lead->ip);
        $this->assertSame('Mozilla/5.0 TestAgent', $lead->user_agent);
    }
}
