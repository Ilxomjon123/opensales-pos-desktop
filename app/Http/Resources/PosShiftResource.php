<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PosShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosShift
 */
final class PosShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'opening_cash' => (int) $this->opening_cash,
            'closing_cash' => $this->closing_cash !== null ? (int) $this->closing_cash : null,
            'expected_cash' => $this->expected_cash !== null ? (int) $this->expected_cash : null,
            'cash_diff' => $this->cash_diff !== null ? (int) $this->cash_diff : null,
            'total_sales' => (int) $this->total_sales,
            'total_cash' => (int) $this->total_cash,
            'total_card' => (int) $this->total_card,
            'total_debt' => (int) $this->total_debt,
            'sales_count' => (int) $this->sales_count,
            'opening_note' => $this->opening_note,
            'closing_note' => $this->closing_note,
            'cashier' => $this->whenLoaded('cashier', fn () => [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
            ]),
        ];
    }
}
