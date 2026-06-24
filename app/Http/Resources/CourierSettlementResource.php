<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CourierSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourierSettlement
 */
final class CourierSettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (int) $this->amount,
            'note' => $this->note,
            'settled_at' => $this->settled_at?->toIso8601String(),
            'settled_by' => $this->whenLoaded('settledBy', fn () => $this->settledBy ? [
                'id' => $this->settledBy->id,
                'name' => $this->settledBy->name,
            ] : null),
        ];
    }
}
