<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\BotVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canManageDealer() && $user->dealer_id !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'bot_token' => [
                'nullable',
                'string',
                'regex:/^\d+:[A-Za-z0-9_-]+$/',
                Rule::unique('dealers', 'bot_token')->ignore($this->user()->dealer_id),
            ],
            'telegram_chat_id' => ['nullable', 'integer'],
            'min_order_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'marketplace_min_order_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'show_out_of_stock' => ['sometimes', 'boolean'],
            'notify_on_price_change' => ['sometimes', 'boolean'],
            'notify_on_new_product' => ['sometimes', 'boolean'],
            'bot_display_name' => ['sometimes', 'nullable', 'string', 'max:64'],
            'bot_short_description' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bot_description' => ['sometimes', 'nullable', 'string', 'max:512'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'visibility' => ['sometimes', 'required', Rule::enum(BotVisibility::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'bot_token.regex' => 'Bot token formati noto\'g\'ri (masalan: 1234567890:AAEhBOweik6ad7Zd...)',
            'bot_token.unique' => 'Bu token boshqa dillerga tegishli',
            'min_order_amount.integer' => 'Minimal buyurtma summasi butun son bo\'lishi kerak.',
            'min_order_amount.min' => 'Minimal buyurtma summasi manfiy bo\'la olmaydi.',
            'bot_display_name.max' => 'Bot nomi 64 belgidan oshmasligi kerak.',
            'bot_short_description.max' => 'Qisqa tavsif 120 belgidan oshmasligi kerak.',
            'bot_description.max' => 'To\'liq tavsif 512 belgidan oshmasligi kerak.',
            'contact_phone.max' => 'Telefon raqami 32 belgidan oshmasligi kerak.',
            'visibility.required' => 'Bot ko\'rinishini tanlang.',
            'visibility.enum' => 'Bot ko\'rinishi noto\'g\'ri.',
        ];
    }
}
