<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use App\Enums\Currency;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class MarketplaceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->displayNumber(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total' => (int) $this->total,
            'currency' => ($this->currency ?? Currency::UZS)->symbol(),
            'note' => $this->note,
            'cancellation_reason' => $this->cancellation_reason,
            'seller' => $this->whenLoaded('dealer', fn () => [
                'id' => $this->dealer->id,
                'name' => $this->dealer->name,
                'phone' => $this->dealer->contact_phone,
            ]),
            'buyer' => $this->whenLoaded('buyerDealer', fn () => $this->buyerDealer !== null ? [
                'id' => $this->buyerDealer->id,
                'name' => $this->buyerDealer->name,
                'phone' => $this->buyerDealer->contact_phone,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'price' => (float) $item->price,
                'pack_price' => $item->pack_price !== null ? (float) $item->pack_price : null,
                'unit' => $item->unit?->value,
                'pack_size' => (float) $item->pack_size,
                'qty' => (float) $item->qty,
                'pack_qty' => $item->pack_qty,
                'subtotal' => $item->subtotal(),
            ])->values()),
            'confirmed_at' => $this->assembling_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
