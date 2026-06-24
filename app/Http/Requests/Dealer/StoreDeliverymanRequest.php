<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class StoreDeliverymanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDealer() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:5', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()],
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
