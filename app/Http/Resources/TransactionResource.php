<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ReturnReason;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'note' => $this->note,
            'reason' => $this->reason,
            'reason_label' => $this->reason !== null && ReturnReason::tryFrom($this->reason) !== null
                ? ReturnReason::from($this->reason)->label()
                : $this->reason,
            'actor_name' => $this->actor_name,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
            'shop_id' => $this->shop_id,
            'shop' => $this->whenLoaded('shop', fn () => $this->shop ? [
                'id' => $this->shop->id,
                'name' => $this->shop->name,
            ] : null),
            'order_id' => $this->order_id,
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'number' => $this->order->displayNumber(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'items_count' => $this->whenLoaded('details', fn () => $this->details->count()),
            'total_qty' => $this->whenLoaded(
                'details',
                fn (): float => (float) $this->details->sum('qty'),
            ),
            'total_cost' => $this->whenLoaded(
                'details',
                fn (): ?int => $this->details->whereNotNull('unit_cost')->isEmpty()
                    ? null
                    : (int) round($this->details->sum(fn ($d): float => ((float) ($d->unit_cost ?? 0)) * (float) $d->qty)),
            ),
            'details' => TransactionDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
