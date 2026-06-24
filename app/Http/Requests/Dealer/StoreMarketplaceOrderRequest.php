<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\ProductVisibility;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMarketplaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->dealer_id !== null;
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where(function (Builder $query) use ($dealerId): void {
                    $query->where('is_active', true)
                        ->where('dealer_id', '!=', $dealerId)
                        ->whereIn('visibility', [
                            ProductVisibility::MARKETPLACE_ONLY->value,
                            ProductVisibility::BOTH->value,
                        ]);
                }),
            ],
            'items.*.qty' => ['required', 'numeric', 'min:0.001', 'max:1000000'],
            'items.*.pack_qty' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
