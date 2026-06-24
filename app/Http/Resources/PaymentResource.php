<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'cardholder_name' => $this->cardholder_name,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'shop' => ShopResource::make($this->whenLoaded('shop')),
        ];
    }
}
