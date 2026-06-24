<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReorderProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1', 'max:5000'],
            'order.*' => [
                'integer',
                Rule::exists('products', 'id')->where('dealer_id', $this->user()->dealer_id),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('order')));
    }
}
