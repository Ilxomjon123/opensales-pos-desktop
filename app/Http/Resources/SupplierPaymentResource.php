<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin SupplierPayment
 */
final class SupplierPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $transaction = $this->whenLoaded('transaction');
        $txType = $transaction !== null && ! ($transaction instanceof MissingValue)
            ? $this->transaction?->type
            : null;

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'cardholder_name' => $this->cardholder_name,
            'note' => $this->note,
            'transaction_id' => $this->transaction_id,
            'transaction_type' => $txType?->value,
            'transaction_type_label' => $txType?->label(),
            'created_at' => $this->created_at?->toIso8601String(),
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
        ];
    }
}
