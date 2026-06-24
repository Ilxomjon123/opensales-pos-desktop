<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_type_id' => $this->product_type_id,
            'product_name' => $this->product_name,
            'product_type_name' => $this->product_type_name,
            'product_type_code' => $this->product_type_code,
            'display_name' => $this->displayName(),
            'price' => (float) $this->price,
            'pack_price' => $this->pack_price !== null ? (float) $this->pack_price : null,
            'qty' => (float) $this->qty,
            'delivered_qty' => (float) $this->delivered_qty,
            'delivered_pack_qty' => $this->delivered_pack_qty,
            'picked_qty' => $this->picked_qty !== null ? (float) $this->picked_qty : null,
            'picked_pack_qty' => $this->picked_pack_qty,
            'returned_qty' => (float) ($this->returned_qty ?? 0),
            'returned_pack_qty' => $this->returned_pack_qty,
            'carry_qty' => $this->carryQty(),
            'carry_pack_qty' => $this->carryPackQty(),
            'carry_subtotal' => $this->carrySubtotal(),
            'unit' => $this->unit?->value,
            'pack_size' => (float) $this->pack_size,
            'pack_qty' => $this->pack_qty,
            'subtotal' => $this->subtotal(),
            'delivered_subtotal' => $this->deliveredSubtotal(),
            'prepared_subtotal' => $this->preparedSubtotal(),
        ];
    }
}
