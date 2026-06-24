<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Pos;

use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Enums\ShopType;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canRunPos() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'customer_id' => [
                'required', 'integer',
                Rule::exists('shops', 'id')
                    ->where('dealer_id', $dealerId)
                    ->whereIn('type', [ShopType::WALK_IN->value, ShopType::INDIVIDUAL->value])
                    ->where('is_active', true),
            ],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')
                    ->where('dealer_id', $dealerId)
                    ->where('is_active', true),
            ],
            'items.*.product_type_id' => [
                'nullable', 'integer',
                Rule::exists('product_types', 'id')->where('is_active', true),
            ],
            'items.*.qty' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'items.*.pack_qty' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'items.*.pack_price' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],

            'paid_cash' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'paid_card' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'cardholder_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ((int) $this->input('paid_card', 0) > 0
                && trim((string) $this->input('cardholder_name', '')) === ''
            ) {
                $v->errors()->add('cardholder_name', 'Karta orqali to\'lovda karta egasi ism-familiyasi majburiy');
            }

            $this->validateQtyByUnit($v);
        });
    }

    /**
     * DONA birlikdagi mahsulotlar uchun qty butun son bo'lishi shart.
     * KG/litr kabi birliklarga 0.001 daqiqalik kasr ruxsat.
     */
    private function validateQtyByUnit(Validator $v): void
    {
        $items = is_array($this->input('items')) ? $this->input('items') : [];
        $productIds = array_values(array_unique(array_filter(array_map(
            static fn ($r): int => (int) ($r['product_id'] ?? 0),
            $items,
        ))));
        if ($productIds === []) {
            return;
        }

        $units = Product::query()
            ->whereIn('id', $productIds)
            ->where('dealer_id', (int) $this->user()->dealer_id)
            ->pluck('unit', 'id');

        foreach ($items as $i => $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['qty'] ?? 0);
            $unit = $units->get($productId);
            $unitEnum = $unit instanceof ProductUnit ? $unit : ProductUnit::tryFrom((string) $unit);
            if ($unitEnum === ProductUnit::DONA && fmod($qty, 1.0) !== 0.0) {
                $v->errors()->add("items.{$i}.qty", '"dona" birlikdagi mahsulotda miqdor butun son bo\'lishi kerak');
            }
        }
    }

    public function paymentMethodHint(): PaymentMethod
    {
        return (int) $this->input('paid_card', 0) > 0 ? PaymentMethod::CARD : PaymentMethod::CASH;
    }
}
