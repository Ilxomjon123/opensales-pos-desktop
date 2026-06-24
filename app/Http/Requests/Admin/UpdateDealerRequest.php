<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CommissionType;
use App\Enums\Currency;
use App\Models\Dealer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateDealerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var Dealer $dealer */
        $dealer = $this->route('dealer');
        $dealerId = (int) $dealer->id;
        $userId = $dealer->owner?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes', 'required', 'string', 'min:5', 'max:255', 'regex:/^[A-Za-z0-9._@-]+$/',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'bot_token' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('dealers', 'bot_token')->ignore($dealerId),
                'regex:/^\d+:[\w-]+$/',
            ],
            'telegram_chat_id' => ['nullable', 'integer'],
            'min_order_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'country_id' => ['sometimes', 'nullable', 'integer', Rule::exists('countries', 'id')],
            'currency' => ['sometimes', 'nullable', new Enum(Currency::class)],

            // Birja (marketplace) sotuvchisi sozlamalari
            'sells_on_marketplace' => ['sometimes', 'boolean'],
            'marketplace_commission_type' => ['sometimes', 'nullable', new Enum(CommissionType::class)],
            'marketplace_platform_fee_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'marketplace_fixed_commission_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Foydalanuvchi nomi faqat lotin harflari, raqamlar va . _ - @ dan iborat bo\'lishi mumkin.',
            'min_order_amount.integer' => 'Minimal buyurtma summasi butun son bo\'lishi kerak.',
            'min_order_amount.min' => 'Minimal buyurtma summasi manfiy bo\'la olmaydi.',
        ];
    }
}
