<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Pos;

use Illuminate\Foundation\Http\FormRequest;

final class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canRunPos() ?? false;
    }

    public function rules(): array
    {
        return [
            'closing_cash' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'closing_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
