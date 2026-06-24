<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDealer() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'supplier_id' => [
                'required', 'integer',
                Rule::exists('suppliers', 'id')->where('dealer_id', $dealerId),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', new Enum(PaymentType::class)],
            'method' => ['nullable', new Enum(PaymentMethod::class)],
            'cardholder_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->method() !== PaymentMethod::CARD) {
                return;
            }

            if (trim((string) $this->input('cardholder_name', '')) === '') {
                $validator->errors()->add(
                    'cardholder_name',
                    'Karta orqali to\'lovda karta egasi ism-familiyasi majburiy'
                );
            }
        });
    }

    public function type(): PaymentType
    {
        return PaymentType::from($this->string('type')->toString());
    }

    public function method(): PaymentMethod
    {
        $value = $this->string('method')->toString();

        return $value !== '' ? PaymentMethod::from($value) : PaymentMethod::CASH;
    }
}
