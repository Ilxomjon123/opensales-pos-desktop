<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProfitReportFilterRequest extends FormRequest
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
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('product_categories', 'id')->where('dealer_id', $dealerId),
            ],
            'product_id' => [
                'nullable', 'integer',
                Rule::exists('products', 'id')->where('dealer_id', $dealerId),
            ],
        ];
    }

    /**
     * @return array{
     *     date_from: string|null, date_to: string|null,
     *     category_id: int|null, product_id: int|null,
     * }
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'category_id' => $this->input('category_id') !== null && $this->input('category_id') !== '' ? (int) $this->input('category_id') : null,
            'product_id' => $this->input('product_id') !== null && $this->input('product_id') !== '' ? (int) $this->input('product_id') : null,
        ];
    }
}
