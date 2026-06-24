<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\PosShiftStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\PosShiftException;
use App\Models\Dealer;
use App\Models\User;
use App\Services\PosShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PosShiftServiceTest extends TestCase
{
    use RefreshDatabase;

    private PosShiftService $service;

    private User $cashier;

    private Dealer $dealer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PosShiftService::class);
        $this->dealer = Dealer::factory()->create();
        $this->cashier = User::factory()->create([
            'role' => UserRole::CASHIER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_open_creates_shift_for_cashier(): void
    {
        $shift = $this->service->open($this->cashier, openingCash: 50_000);

        $this->assertSame(PosShiftStatus::OPEN, $shift->status);
        $this->assertSame($this->cashier->id, $shift->cashier_user_id);
        $this->assertSame(50_000, $shift->opening_cash);
    }

    public function test_open_throws_when_already_open(): void
    {
        $this->service->open($this->cashier, openingCash: 0);

        $this->expectException(PosShiftException::class);
        $this->service->open($this->cashier, openingCash: 0);
    }

    public function test_close_records_expected_and_diff(): void
    {
        $shift = $this->service->open($this->cashier, openingCash: 100_000);

        $closed = $this->service->close($shift, closingCash: 105_000);

        $this->assertSame(PosShiftStatus::CLOSED, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame(100_000, $closed->expected_cash);
        $this->assertSame(5_000, $closed->cash_diff);
    }

    public function test_close_throws_when_already_closed(): void
    {
        $shift = $this->service->open($this->cashier, openingCash: 0);
        $this->service->close($shift, closingCash: 0);

        $this->expectException(PosShiftException::class);
        $this->service->close($shift->fresh(), closingCash: 0);
    }
}
