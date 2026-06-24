<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDealer() ?? false;
    }

    public function rules(): array
    {
        $cancelling = $this->string('status')->toString() === OrderStatus::CANCELLED->value;

        return [
            'status' => ['required', new Enum(OrderStatus::class)],
            'cancellation_reason' => [
                $cancelling ? 'required' : 'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function status(): OrderStatus
    {
        return OrderStatus::from($this->string('status')->toString());
    }
}
