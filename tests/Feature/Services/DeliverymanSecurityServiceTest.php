<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Dealer;
use App\Models\DeliverymanRequestLog;
use App\Models\User;
use App\Services\DeliverymanSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class DeliverymanSecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliverymanSecurityService $service;

    private User $deliveryman;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DeliverymanSecurityService::class);

        $dealer = Dealer::factory()->create();
        $this->deliveryman = User::factory()->deliveryman($dealer->id)->create();
    }

    public function test_first_request_from_single_ip_is_allowed_and_logged(): void
    {
        $this->assertTrue($this->service->track($this->deliveryman, '1.1.1.1'));

        $this->assertSame(1, DeliverymanRequestLog::query()->count());
        $this->assertSame(0, $this->service->violationCount($this->deliveryman->id));
    }

    public function test_sequential_ip_change_outside_window_is_not_violation(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 12, 0, 0));
        $this->service->track($this->deliveryman, '1.1.1.1');

        // 31 soniyadan keyin boshqa IP — wifi → 4G almashtirish stsenariysi
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 12, 0, 31));
        $allowed = $this->service->track($this->deliveryman, '2.2.2.2');

        $this->assertTrue($allowed);
        $this->assertSame(0, $this->service->violationCount($this->deliveryman->id));

        Carbon::setTestNow();
    }

    public function test_first_concurrent_ip_logs_violation_but_allows_request(): void
    {
        $this->service->track($this->deliveryman, '1.1.1.1');

        $allowed = $this->service->track($this->deliveryman, '9.9.9.9');

        $this->assertTrue($allowed, 'Birinchi buzilish — faqat ogohlantirish, request davom etadi');
        $this->assertSame(1, $this->service->violationCount($this->deliveryman->id));
    }

    public function test_second_violation_triggers_soft_block(): void
    {
        $this->service->track($this->deliveryman, '1.1.1.1');
        $this->service->track($this->deliveryman, '9.9.9.9');         // 1-buzilish, allowed
        $allowed = $this->service->track($this->deliveryman, '1.1.1.1'); // 2-buzilish, soft block

        $this->assertFalse($allowed);
        $this->assertSame(2, $this->service->violationCount($this->deliveryman->id));
        $this->assertNull($this->deliveryman->fresh()->security_locked_until);
    }

    public function test_third_violation_applies_temporary_lock_one_hour(): void
    {
        $this->service->track($this->deliveryman, '1.1.1.1');
        $this->service->track($this->deliveryman, '9.9.9.9');         // 1
        $this->service->track($this->deliveryman, '1.1.1.1');         // 2
        $allowed = $this->service->track($this->deliveryman, '9.9.9.9'); // 3

        $this->assertFalse($allowed);
        $this->assertSame(3, $this->service->violationCount($this->deliveryman->id));

        $fresh = $this->deliveryman->fresh();
        $this->assertNotNull($fresh->security_locked_until);
        $this->assertTrue($fresh->security_locked_until->isFuture());
        $this->assertTrue(
            $fresh->security_locked_until->lessThanOrEqualTo(Carbon::now()->addMinutes(60)->addSecond()),
            'Temp lock muddati 1 soatdan oshmasligi kerak',
        );
    }

    public function test_fifth_violation_applies_hard_lock(): void
    {
        $this->service->track($this->deliveryman, '1.1.1.1');
        $this->service->track($this->deliveryman, '9.9.9.9'); // 1
        $this->service->track($this->deliveryman, '1.1.1.1'); // 2
        $this->service->track($this->deliveryman, '9.9.9.9'); // 3
        $this->service->track($this->deliveryman, '1.1.1.1'); // 4
        $this->service->track($this->deliveryman, '9.9.9.9'); // 5

        $this->assertSame(5, $this->service->violationCount($this->deliveryman->id));

        $fresh = $this->deliveryman->fresh();
        $this->assertNotNull($fresh->security_locked_until);
        // Hard lock — 30 kundan ortiq
        $this->assertTrue($fresh->security_locked_until->greaterThan(Carbon::now()->addDays(29)));
    }

    public function test_unlock_clears_lock_and_violation_counter(): void
    {
        $this->service->track($this->deliveryman, '1.1.1.1');
        $this->service->track($this->deliveryman, '9.9.9.9');
        $this->service->track($this->deliveryman, '1.1.1.1');
        $this->service->track($this->deliveryman, '9.9.9.9'); // 3 → temp lock

        $this->assertNotNull($this->deliveryman->fresh()->security_locked_until);

        $this->service->unlock($this->deliveryman);

        $this->assertNull($this->deliveryman->fresh()->security_locked_until);
        $this->assertSame(0, $this->service->violationCount($this->deliveryman->id));
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
