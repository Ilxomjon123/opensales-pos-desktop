<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CommissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDealerCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'commission_type' => ['required', 'string', Rule::enum(CommissionType::class)],
            'platform_fee_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                Rule::requiredIf(fn (): bool => $this->input('commission_type') === CommissionType::TURNOVER_PERCENTAGE->value),
            ],
            'fixed_commission_amount' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(fn (): bool => in_array(
                    $this->input('commission_type'),
                    [
                        CommissionType::FIXED_PER_SHOP->value,
                        CommissionType::FIXED_PER_ORDER->value,
                        CommissionType::FIXED_PER_DELIVERYMAN->value,
                        CommissionType::FIXED_MONTHLY->value,
                    ],
                    true,
                )),
            ],
        ];
    }
}
