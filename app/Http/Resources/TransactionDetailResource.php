<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransactionDetail
 */
final class TransactionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayName = $this->product_type_name !== null
            ? "{$this->product_name} — {$this->product_type_name}"
            : (string) $this->product_name;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_type_id' => $this->product_type_id,
            'order_item_id' => $this->order_item_id,
            'product_name' => $this->product_name,
            'product_type_name' => $this->product_type_name,
            'display_name' => $displayName,
            'qty' => (float) $this->qty,
            'pack_qty' => $this->pack_qty,
            'unit_cost' => $this->unit_cost === null ? null : (float) $this->unit_cost,
            'pack_unit_cost' => $this->pack_unit_cost === null ? null : (float) $this->pack_unit_cost,
            'line_total' => $this->unit_cost === null ? null : (int) round((float) $this->unit_cost * (float) $this->qty),
            'stock_before' => $this->stock_before === null ? null : (float) $this->stock_before,
            'stock_after' => $this->stock_after === null ? null : (float) $this->stock_after,
            'disposition' => $this->disposition?->value,
            'disposition_label' => $this->disposition?->label(),
        ];
    }
}
