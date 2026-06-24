<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\ProductUnit;
use App\Enums\ProductVisibility;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->input('min_stock'), [null, ''], true)) {
            $this->merge(['min_stock' => 0]);
        }

        // has_types=true bo'lsa product darajasidagi narx/stock o'rniga tipdan olinadi
        if ($this->boolean('has_types')) {
            $this->merge([
                'price' => (float) ($this->input('price') ?? 0),
                'stock' => (float) ($this->input('stock') ?? 0),
                'pack_size' => max(1.0, (float) ($this->input('pack_size') ?? 1)),
            ]);
        }
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;
        $hasTypes = $this->boolean('has_types');

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('product_categories', 'id')->where('dealer_id', $dealerId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'pack_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'pack_cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'numeric'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'pack_size' => ['required', 'numeric', 'min:0.001', 'max:10000'],
            'bulk_only' => ['sometimes', 'boolean'],
            'has_types' => ['sometimes', 'boolean'],
            'unit' => ['required', new Enum(ProductUnit::class)],
            'visibility' => ['sometimes', new Enum(ProductVisibility::class)],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['image', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],

            'types' => [$hasTypes ? 'required' : 'sometimes', 'array', 'min:'.($hasTypes ? '1' : '0'), 'max:50'],
            'types.*.name' => ['required_with:types', 'string', 'max:255'],
            'types.*.price' => ['required_with:types', 'numeric', 'min:0'],
            'types.*.pack_price' => ['nullable', 'numeric', 'min:0'],
            'types.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'types.*.pack_cost_price' => ['nullable', 'numeric', 'min:0'],
            'types.*.stock' => ['required_with:types', 'numeric'],
            'types.*.min_stock' => ['nullable', 'numeric', 'min:0'],
            'types.*.pack_size' => ['required_with:types', 'numeric', 'min:0.001', 'max:10000'],
            'types.*.bulk_only' => ['sometimes', 'boolean'],
            'types.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'types.*.is_active' => ['sometimes', 'boolean'],
            'types.*.images' => ['sometimes', 'array', 'max:10'],
            'types.*.images.*' => ['image', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('bulk_only') && (float) $this->input('pack_size', 1) <= 1) {
                $validator->errors()->add(
                    'bulk_only',
                    "Faqat blokda sotish uchun blok hajmi 1 dan katta bo'lishi kerak"
                );
            }

            foreach ((array) $this->input('types', []) as $idx => $type) {
                $bulkOnly = (bool) ($type['bulk_only'] ?? false);
                $packSize = (float) ($type['pack_size'] ?? 1);

                if ($bulkOnly && $packSize <= 1) {
                    $validator->errors()->add(
                        "types.{$idx}.bulk_only",
                        "Faqat blokda sotish uchun blok hajmi 1 dan katta bo'lishi kerak"
                    );
                }
            }
        });
    }
}
