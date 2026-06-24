<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer\Reports;

use Illuminate\Foundation\Http\FormRequest;

final class DailyClosingFilterRequest extends FormRequest
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
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array{date_from: string|null, date_to: string|null}
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
        ];
    }
}
