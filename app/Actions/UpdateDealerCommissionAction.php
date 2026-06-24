<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommissionType;
use App\Models\Dealer;
use App\Services\CommissionHistoryService;
use Illuminate\Support\Facades\DB;

/**
 * Diller komissiya tipini o'zgartiradi.
 *
 * Joriy faol periodni yopadi (ends_at = now) va yangi periodni ochadi.
 * Bu eski oylar eski tip bo'yicha hisoblanishini ta'minlaydi.
 */
final class UpdateDealerCommissionAction
{
    public function __construct(
        private readonly CommissionHistoryService $history,
    ) {}

    public function execute(
        Dealer $dealer,
        CommissionType $type,
        ?float $percentageRate = null,
        ?int $fixedAmount = null,
    ): Dealer {
        $resolvedFixedAmount = $type->usesFixedAmount() ? $fixedAmount : null;

        DB::transaction(function () use ($dealer, $type, $percentageRate, $resolvedFixedAmount): void {
            $now = now();

            $dealer->commissionPeriods()
                ->whereNull('ends_at')
                ->update(['ends_at' => $now, 'updated_at' => $now]);

            $dealer->commissionPeriods()->create([
                'commission_type' => $type,
                'fixed_commission_amount' => $resolvedFixedAmount,
                'starts_at' => $now,
                'ends_at' => null,
            ]);

            $update = [
                'commission_type' => $type,
                'fixed_commission_amount' => $resolvedFixedAmount,
            ];

            if ($type === CommissionType::TURNOVER_PERCENTAGE) {
                $update['platform_fee_rate'] = $percentageRate ?? (float) $dealer->platform_fee_rate;
            }

            $dealer->update($update);
        });

        $this->history->invalidate();

        return $dealer->refresh();
    }
}
