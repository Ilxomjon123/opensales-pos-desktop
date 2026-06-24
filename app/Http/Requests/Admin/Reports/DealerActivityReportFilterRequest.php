<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Reports;

use App\Services\Reports\DealerActivityReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DealerActivityReportFilterRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(DealerActivityReportService::STATUS_OPTIONS)],
        ];
    }

    /**
     * @return array{date_from: ?string, date_to: ?string, status: ?string}
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'status' => $this->input('status'),
        ];
    }
}
