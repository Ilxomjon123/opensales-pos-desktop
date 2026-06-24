<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreStockTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'supplier_id' => [
                'required', 'integer',
                Rule::exists('suppliers', 'id')->where('dealer_id', $dealerId),
            ],
            'note' => ['nullable', 'string', 'max:500'],
            'paid_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'cardholder_name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('dealer_id', $dealerId),
            ],
            'items.*.product_type_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0.000001', 'max:1000000000'],
            'items.*.pack_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $paid = (int) $this->input('paid_amount', 0);

            if ($paid > 0
                && $this->paymentMethod() === PaymentMethod::CARD
                && trim((string) $this->input('cardholder_name', '')) === '') {
                $validator->errors()->add(
                    'cardholder_name',
                    'Karta orqali to\'lovda karta egasi ism-familiyasi majburiy'
                );
            }

            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            $signatures = [];

            foreach ($items as $idx => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $typeId = isset($item['product_type_id']) && (int) $item['product_type_id'] > 0
                    ? (int) $item['product_type_id']
                    : 0;
                $sig = $productId.':'.$typeId;

                if (isset($signatures[$sig])) {
                    $validator->errors()->add(
                        "items.{$idx}.product_id",
                        'Bir mahsulot/tip bir nechta marta kelmasligi kerak.'
                    );
                }

                $signatures[$sig] = true;
            }
        });
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Ta\'minotchini tanlang.',
            'supplier_id.exists' => 'Ta\'minotchi topilmadi.',
            'items.*.unit_cost.required' => 'Tannarx (sotib olish narxi) majburiy.',
            'items.*.unit_cost.min' => 'Tannarx 0.01 so\'mdan kam bo\'lmasligi kerak.',
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        $value = $this->string('payment_method')->toString();

        return $value !== '' ? PaymentMethod::from($value) : PaymentMethod::CASH;
    }
}
