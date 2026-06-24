<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreMarketplacePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->dealer_id !== null;
    }

    public function rules(): array
    {
        return [
            'partner_dealer_id' => [
                'required', 'integer', 'different:_self',
                Rule::exists('dealers', 'id'),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'cardholder_name' => ['nullable', 'string', 'max:255', 'required_if:method,card'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['_self' => (string) $this->user()?->dealer_id]);
    }
}
