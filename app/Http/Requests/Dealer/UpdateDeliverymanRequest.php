<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateDeliverymanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDealer() ?? false;
    }

    public function rules(): array
    {
        $id = (int) ($this->route('deliveryman')?->id ?? 0);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes', 'required', 'string', 'min:5', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($id),
            ],
            'phone' => ['sometimes', 'required', 'string', 'max:32'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Bu foydalanuvchi nomi allaqachon band.',
            'username.regex' => 'Foydalanuvchi nomi faqat lotin harflari, raqamlar va . _ - dan iborat bo\'lishi mumkin.',
        ];
    }
}
