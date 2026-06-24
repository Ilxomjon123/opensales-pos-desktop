<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EditOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tahrirlash huquqi OrderPolicy::edit orqali tekshiriladi.
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'items' => ['present', 'array'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('dealer_id', $dealerId),
            ],
            'items.*.product_type_id' => ['nullable', 'integer'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.pack_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.delivered_qty' => ['required', 'numeric', 'min:0', 'max:999999'],
            'items.*.delivered_pack_qty' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'paid_amount' => ['required', 'integer', 'min:0'],
            'paid_card' => ['nullable', 'integer', 'min:0', 'lte:paid_amount'],
            'cardholder_name' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $paidCard = (int) $this->input('paid_card', 0);
            $cardholder = trim((string) $this->input('cardholder_name', ''));

            if ($paidCard > 0 && $cardholder === '') {
                $validator->errors()->add(
                    'cardholder_name',
                    'Karta orqali to\'lovda karta egasi ism-familiyasi majburiy'
                );
            }

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

                $deliveredQty = (float) ($item['delivered_qty'] ?? 0);
                $deliveredPackQty = (int) ($item['delivered_pack_qty'] ?? 0);

                if ($deliveredQty <= 0 && $deliveredPackQty === 0) {
                    continue;
                }

                if ($typeId !== null) {
                    $type = $bulkTypes->get($typeId);

                    if ($type === null) {
                        continue;
                    }

                    $packSize = max(1.0, (float) $type->pack_size);

                    if ($deliveredPackQty < 1 || abs($deliveredQty - $deliveredPackQty * $packSize) > 0.0005) {
                        $label = $type->product?->name
                            ? "{$type->product->name} — {$type->name}"
                            : $type->name;
                        $validator->errors()->add(
                            "items.{$idx}.delivered_qty",
                            "'{$label}' faqat blokda yetkazib beriladi"
                        );
                    }

                    continue;
                }

                $product = $bulkProducts->get($productId);

                if ($product === null) {
                    continue;
                }

                $packSize = max(1.0, (float) $product->pack_size);

                if ($deliveredPackQty < 1 || abs($deliveredQty - $deliveredPackQty * $packSize) > 0.0005) {
                    $validator->errors()->add(
                        "items.{$idx}.delivered_qty",
                        "'{$product->name}' faqat blokda yetkazib beriladi"
                    );
                }
            }
        });
    }
}
