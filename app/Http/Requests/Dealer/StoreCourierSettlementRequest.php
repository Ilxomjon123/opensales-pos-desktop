<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCourierSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
