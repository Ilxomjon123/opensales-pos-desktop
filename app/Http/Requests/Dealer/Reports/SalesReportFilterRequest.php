<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Reports;

use App\Enums\OrderStatus;
use App\Services\Reports\SalesReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalesReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDealer() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dealerId = (int) $this->user()->dealer_id;

        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'group_by' => ['nullable', Rule::in(SalesReportService::GROUP_BY_OPTIONS)],
            'shop_id' => [
                'nullable', 'integer',
                Rule::exists('shops', 'id')->where('dealer_id', $dealerId),
            ],
            'deliveryman_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('dealer_id', $dealerId),
            ],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('product_categories', 'id')->where('dealer_id', $dealerId),
            ],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => [Rule::in(array_column(OrderStatus::cases(), 'value'))],
        ];
    }

    /**
     * @return array{
     *     date_from: string|null, date_to: string|null, group_by: string|null,
     *     shop_id: int|null, deliveryman_id: int|null, category_id: int|null,
     *     statuses: list<string>|null,
     * }
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'group_by' => $this->input('group_by'),
            'shop_id' => $this->input('shop_id') !== null && $this->input('shop_id') !== '' ? (int) $this->input('shop_id') : null,
            'deliveryman_id' => $this->input('deliveryman_id') !== null && $this->input('deliveryman_id') !== '' ? (int) $this->input('deliveryman_id') : null,
            'category_id' => $this->input('category_id') !== null && $this->input('category_id') !== '' ? (int) $this->input('category_id') : null,
            'statuses' => $this->input('statuses'),
        ];
    }
}
