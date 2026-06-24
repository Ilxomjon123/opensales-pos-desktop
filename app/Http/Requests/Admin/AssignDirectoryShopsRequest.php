<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignDirectoryShopsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'directory_ids' => ['required', 'array', 'min:1', 'max:200'],
            'directory_ids.*' => ['integer', Rule::exists('directory_shops', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'directory_ids.required' => 'Kamida bitta mijoz tanlang.',
            'directory_ids.max' => 'Bir vaqtda 200 tadan ko\'p mijoz biriktirib bo\'lmaydi.',
        ];
    }
}
