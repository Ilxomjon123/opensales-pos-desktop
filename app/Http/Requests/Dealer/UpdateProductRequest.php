<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\ProductUnit;
use App\Enums\ProductVisibility;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('min_stock') && in_array($this->input('min_stock'), [null, ''], true)) {
            $this->merge(['min_stock' => 0]);
        }
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;
        $hasTypes = $this->boolean('has_types');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('product_categories', 'id')->where('dealer_id', $dealerId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'pack_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'pack_cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'required', 'numeric'],
            'min_stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'pack_size' => ['sometimes', 'required', 'numeric', 'min:0.001', 'max:10000'],
            'bulk_only' => ['sometimes', 'boolean'],
            'has_types' => ['sometimes', 'boolean'],
            'unit' => ['sometimes', 'required', new Enum(ProductUnit::class)],
            'visibility' => ['sometimes', new Enum(ProductVisibility::class)],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['image', 'max:5120'],
            'remove_image_ids' => ['sometimes', 'array'],
            'remove_image_ids.*' => ['integer'],
            'image_order' => ['sometimes', 'array'],
            'image_order.*' => ['string', 'regex:/^(ex:\d+|new:\d+)$/'],
            'is_active' => ['sometimes', 'boolean'],

            'removed_type_ids' => ['sometimes', 'array'],
            'removed_type_ids.*' => ['integer'],

            'types' => [$hasTypes ? 'required' : 'sometimes', 'array', 'min:'.($hasTypes ? '1' : '0'), 'max:50'],
            'types.*.id' => ['nullable', 'integer'],
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
            'types.*.remove_image_ids' => ['sometimes', 'array'],
            'types.*.remove_image_ids.*' => ['integer'],
            'types.*.image_order' => ['sometimes', 'array'],
            'types.*.image_order.*' => ['string', 'regex:/^(ex:\d+|new:\d+)$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->has('bulk_only')) {
                $bulkOnly = $this->boolean('bulk_only');
                $packSize = (float) $this->input(
                    'pack_size',
                    $this->route('product')?->pack_size ?? 1,
                );

                if ($bulkOnly && $packSize <= 1) {
                    $validator->errors()->add(
                        'bulk_only',
                        "Faqat blokda sotish uchun blok hajmi 1 dan katta bo'lishi kerak"
                    );
                }
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
