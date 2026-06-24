<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOrderMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDealer() ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Xabar matni majburiy',
        ];
    }
}
