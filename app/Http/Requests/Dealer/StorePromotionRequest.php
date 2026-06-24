<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\PromotionScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDealer() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', new Enum(PromotionScope::class)],
            'target_id' => [
                'nullable',
                'integer',
                'required_if:scope,product',
                'required_if:scope,category',
                'prohibited_if:scope,all',
                Rule::when(
                    $this->input('scope') === 'product',
                    [Rule::exists('products', 'id')->where('dealer_id', $dealerId)],
                ),
                Rule::when(
                    $this->input('scope') === 'category',
                    [Rule::exists('product_categories', 'id')->where('dealer_id', $dealerId)],
                ),
            ],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
