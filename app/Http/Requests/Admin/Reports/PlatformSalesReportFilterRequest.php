<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Reports;

use App\Services\Reports\PlatformSalesReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlatformSalesReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'group_by' => ['nullable', Rule::in(PlatformSalesReportService::GROUP_BY_OPTIONS)],
            'dealer_id' => ['nullable', 'integer', Rule::exists('dealers', 'id')],
        ];
    }

    /**
     * @return array{date_from: ?string, date_to: ?string, group_by: ?string, dealer_id: ?int}
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'group_by' => $this->input('group_by'),
            'dealer_id' => $this->input('dealer_id') !== null && $this->input('dealer_id') !== '' ? (int) $this->input('dealer_id') : null,
        ];
    }
}
