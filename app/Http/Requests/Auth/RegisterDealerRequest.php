<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterDealerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ochiq registratsiya — istalgan mehmon ro'yxatdan o'ta oladi.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:5', 'max:255', 'regex:/^[A-Za-z0-9._@-]+$/', 'unique:users,username'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{7,}$/'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'commission_type' => ['required', 'string', Rule::in(array_keys((array) config('tariffs.plans')))],
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'commission_type.required' => 'Tarifni tanlang.',
            'commission_type.in' => 'Tanlangan tarif noto\'g\'ri.',
            'phone.required' => 'Telefon raqamingizni kiriting — siz bilan bog\'lanamiz.',
            'phone.regex' => 'Telefon raqami noto\'g\'ri. Masalan: +998 90 123 45 67',
            'username.unique' => 'Bu foydalanuvchi nomi allaqachon band.',
            'username.regex' => 'Foydalanuvchi nomi faqat lotin harflari, raqamlar va . _ - @ dan iborat bo\'lishi mumkin.',
            'password.confirmed' => 'Parol tasdig\'i mos kelmadi.',
        ];
    }
}
