<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkAdjustPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'scope' => ['required', Rule::in(['all', 'category'])],
            'category_id' => [
                'nullable',
                'integer',
                'required_if:scope,category',
                Rule::exists('product_categories', 'id')->where('dealer_id', $dealerId),
            ],
            'mode' => ['required', Rule::in(['percent', 'amount'])],
            'direction' => ['required', Rule::in(['up', 'down'])],
            'value' => ['required', 'numeric', 'min:0.01'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }
}
