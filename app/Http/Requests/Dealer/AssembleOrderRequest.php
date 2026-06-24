<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssembleOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $dealerId = (int) ($this->user()?->dealer_id ?? 0);

        return [
            'items' => ['sometimes', 'array'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('dealer_id', $dealerId),
            ],
            'items.*.product_type_id' => ['nullable', 'integer'],
            'items.*.picked_qty' => ['required', 'numeric', 'min:0', 'max:999999'],
            'items.*.picked_pack_qty' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = (array) $this->input('items', []);

            $productIds = collect($items)->pluck('product_id')->filter()->unique()->all();
            $typeIds = collect($items)->pluck('product_type_id')->filter()->unique()->all();

            if ($productIds === [] && $typeIds === []) {
                return;
            }

            $bulkProducts = $productIds === []
                ? collect()
                : Product::query()
                    ->whereIn('id', $productIds)
                    ->where('bulk_only', true)
                    ->get(['id', 'name', 'pack_size'])
                    ->keyBy('id');

            $bulkTypes = $typeIds === []
                ? collect()
                : ProductType::query()
                    ->whereIn('id', $typeIds)
                    ->where('bulk_only', true)
                    ->with('product:id,name')
                    ->get(['id', 'product_id', 'name', 'pack_size'])
                    ->keyBy('id');

            foreach ($items as $idx => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $typeId = isset($item['product_type_id']) && (int) $item['product_type_id'] > 0
                    ? (int) $item['product_type_id']
                    : null;

                $pickedQty = (float) ($item['picked_qty'] ?? 0);
                $pickedPackQty = (int) ($item['picked_pack_qty'] ?? 0);

                if ($pickedQty <= 0 && $pickedPackQty === 0) {
                    continue;
                }

                if ($typeId !== null) {
                    $type = $bulkTypes->get($typeId);

                    if ($type === null) {
                        continue;
                    }

                    $packSize = max(1.0, (float) $type->pack_size);

                    if ($pickedPackQty < 1 || abs($pickedQty - $pickedPackQty * $packSize) > 0.0005) {
                        $label = $type->product?->name
                            ? "{$type->product->name} — {$type->name}"
                            : $type->name;
                        $validator->errors()->add(
                            "items.{$idx}.picked_qty",
                            "'{$label}' faqat blokda chiqariladi"
                        );
                    }

                    continue;
                }

                $product = $bulkProducts->get($productId);

                if ($product === null) {
                    continue;
                }

                $packSize = max(1.0, (float) $product->pack_size);

                if ($pickedPackQty < 1 || abs($pickedQty - $pickedPackQty * $packSize) > 0.0005) {
                    $validator->errors()->add(
                        "items.{$idx}.picked_qty",
                        "'{$product->name}' faqat blokda chiqariladi"
                    );
                }
            }
        });
    }
}
