<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\ReturnDisposition;
use App\Enums\ReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreShopReturnFreeformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInventory() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'reason' => ['required', new Enum(ReturnReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'paid_cash' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'paid_card' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'cardholder_name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('dealer_id', $dealerId),
            ],
            'items.*.product_type_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001', 'max:1000000'],
            'items.*.pack_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'items.*.pack_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'items.*.disposition' => ['required', new Enum(ReturnDisposition::class)],
        ];
    }

    public function paidCash(): int
    {
        return max(0, (int) $this->input('paid_cash', 0));
    }

    public function paidCard(): int
    {
        return max(0, (int) $this->input('paid_card', 0));
    }
}
