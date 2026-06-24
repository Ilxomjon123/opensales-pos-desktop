<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Pos;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StorePosCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canRunPos() ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'cardholder_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->method() === PaymentMethod::CARD
                && trim((string) $this->input('cardholder_name', '')) === ''
            ) {
                $v->errors()->add('cardholder_name', 'Karta orqali to\'lovda karta egasi ism-familiyasi majburiy');
            }
        });
    }

    public function method(): PaymentMethod
    {
        $value = $this->string('method')->toString();

        return $value !== '' ? PaymentMethod::from($value) : PaymentMethod::CASH;
    }
}
