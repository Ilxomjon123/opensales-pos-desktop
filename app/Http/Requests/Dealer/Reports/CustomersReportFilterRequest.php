<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Reports;

use App\Services\Reports\CustomersReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomersReportFilterRequest extends FormRequest
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
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'activity' => ['nullable', Rule::in(CustomersReportService::ACTIVITY_OPTIONS)],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{
     *     date_from: string|null, date_to: string|null,
     *     activity: string|null, region: string|null, district: string|null,
     * }
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'activity' => $this->input('activity'),
            'region' => $this->input('region'),
            'district' => $this->input('district'),
        ];
    }
}
