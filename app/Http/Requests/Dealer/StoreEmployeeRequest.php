<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Faqat owner xodim qo'shadi
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:5', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => [
                'required',
                Rule::in([
                    UserRole::DEALER->value,
                    UserRole::WAREHOUSE->value,
                    UserRole::DELIVERYMAN->value,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Bu foydalanuvchi nomi allaqachon band.',
            'username.regex' => 'Foydalanuvchi nomi faqat lotin harflari, raqamlar va . _ - dan iborat bo\'lishi mumkin.',
        ];
    }

    public function role(): UserRole
    {
        return UserRole::from($this->string('role')->toString());
    }
}
