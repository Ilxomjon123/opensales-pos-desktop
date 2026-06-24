<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignDeliverymanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDealer() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) ($this->user()?->dealer_id ?? 0);

        return [
            'deliveryman_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where('dealer_id', $dealerId)
                    ->where('role', UserRole::DELIVERYMAN->value),
            ],
        ];
    }
}
