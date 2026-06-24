<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Pos;

use Illuminate\Foundation\Http\FormRequest;

final class StorePosCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canRunPos() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
