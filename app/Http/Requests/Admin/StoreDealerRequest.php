<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreDealerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:5', 'max:255', 'regex:/^[A-Za-z0-9._@-]+$/', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'currency' => ['nullable', new Enum(Currency::class)],
            'bot_token' => ['nullable', 'string', 'max:255', 'unique:dealers,bot_token', 'regex:/^\d+:[\w-]+$/'],
            'bot_username' => ['nullable', 'string', 'max:255', 'unique:dealers,bot_username'],
            'telegram_chat_id' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'bot_token.regex' => 'Bot token formati noto\'g\'ri. Masalan: 123456789:ABCdefGHI...',
            'bot_token.unique' => 'Bu bot token allaqachon ro\'yxatdan o\'tgan.',
            'username.unique' => 'Bu foydalanuvchi nomi allaqachon band.',
            'username.regex' => 'Foydalanuvchi nomi faqat lotin harflari, raqamlar va . _ - @ dan iborat bo\'lishi mumkin.',
            'min_order_amount.integer' => 'Minimal buyurtma summasi butun son bo\'lishi kerak.',
            'min_order_amount.min' => 'Minimal buyurtma summasi manfiy bo\'la olmaydi.',
        ];
    }
}
