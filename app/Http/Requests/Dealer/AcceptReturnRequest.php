<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AcceptReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $dealerId = (int) ($this->user()?->dealer_id ?? 0);

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('dealer_id', $dealerId),
            ],
            'items.*.product_type_id' => ['nullable', 'integer'],
            'items.*.returned_qty' => ['required', 'numeric', 'min:0', 'max:999999'],
            'items.*.returned_pack_qty' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
