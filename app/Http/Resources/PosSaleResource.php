<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class PosSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'sale_channel' => $this->sale_channel?->value,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status?->label(),
            'total' => (int) $this->total,
            'discount' => (int) ($this->discount ?? 0),
            'paid_amount' => (int) $this->paid_amount,
            'paid_cash' => (int) $this->paid_cash,
            'paid_card' => (int) $this->paid_card,
            'debt_amount' => (int) $this->debt_amount,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'shift_id' => $this->shift_id,
            'shift' => $this->whenLoaded('shift', fn () => $this->shift ? [
                'id' => $this->shift->id,
                'opened_at' => $this->shift->opened_at?->toIso8601String(),
            ] : null),
            'cashier' => $this->whenLoaded('cashier', fn () => $this->cashier ? [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
            ] : null),
            'shop' => $this->whenLoaded('shop', fn () => $this->shop ? [
                'id' => $this->shop->id,
                'name' => $this->shop->name,
                'phone' => $this->shop->phone,
                'type' => $this->shop->type?->value,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)->resolve()),
        ];
    }
}
