<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->dealer_id !== null
            && ($user->isOwner() || $user->isWarehouse());
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'shop_id' => [
                'required', 'integer',
                Rule::exists('shops', 'id')->where('dealer_id', $dealerId),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')
                    ->where('dealer_id', $dealerId)
                    ->where('is_active', true),
            ],
            'items.*.product_type_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.pack_qty' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.pack_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
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

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->get(['id', 'name', 'pack_size', 'bulk_only', 'has_types'])
                ->keyBy('id');

            $types = $typeIds === []
                ? collect()
                : ProductType::query()
                    ->whereIn('id', $typeIds)
                    ->with('product:id,name')
                    ->get(['id', 'product_id', 'name', 'pack_size', 'bulk_only', 'is_active'])
                    ->keyBy('id');

            foreach ($items as $idx => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $typeId = isset($item['product_type_id']) && (int) $item['product_type_id'] > 0
                    ? (int) $item['product_type_id']
                    : null;
                $qty = (float) ($item['qty'] ?? 0);
                $packQty = (int) ($item['pack_qty'] ?? 0);

                $product = $products->get($productId);

                if ($product === null) {
                    continue;
                }

                if ($product->has_types && $typeId === null) {
                    $validator->errors()->add(
                        "items.{$idx}.product_type_id",
                        "'{$product->name}' uchun tip tanlanishi shart"
                    );

                    continue;
                }

                $bulkOnly = false;
                $packSize = max(1.0, (float) $product->pack_size);
                $label = $product->name;

                if ($typeId !== null) {
                    $type = $types->get($typeId);

                    if ($type === null || $type->product_id !== $product->id || ! $type->is_active) {
                        $validator->errors()->add(
                            "items.{$idx}.product_type_id",
                            "'{$product->name}' uchun tanlangan tip mavjud emas"
                        );

                        continue;
                    }

                    $bulkOnly = (bool) $type->bulk_only;
                    $packSize = max(1.0, (float) $type->pack_size);
                    $label = "{$product->name} — {$type->name}";
                } else {
                    $bulkOnly = (bool) $product->bulk_only;
                }

                if ($bulkOnly && $packSize > 1) {
                    if ($packQty < 1 || abs($qty - $packQty * $packSize) > 0.0005) {
                        $validator->errors()->add(
                            "items.{$idx}.qty",
                            "'{$label}' faqat blokda buyurtma qilinadi"
                        );
                    }
                }
            }
        });
    }
}
