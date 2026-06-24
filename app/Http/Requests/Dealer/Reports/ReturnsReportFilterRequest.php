<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Reports;

use App\Enums\ReturnDisposition;
use App\Services\Reports\ReturnsReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReturnsReportFilterRequest extends FormRequest
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
            'source' => ['nullable', Rule::in(ReturnsReportService::SOURCE_OPTIONS)],
            'disposition' => ['nullable', Rule::in(array_column(ReturnDisposition::cases(), 'value'))],
        ];
    }

    /**
     * @return array{
     *     date_from: string|null, date_to: string|null,
     *     source: string|null, disposition: string|null,
     * }
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'source' => $this->input('source'),
            'disposition' => $this->input('disposition'),
        ];
    }
}
