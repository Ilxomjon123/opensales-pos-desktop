<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\FeatureFlag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'country' => ['required', 'string', Rule::exists('countries', 'code')],
            'flag' => ['required', 'string', Rule::enum(FeatureFlag::class)],
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function flag(): FeatureFlag
    {
        return FeatureFlag::from($this->string('flag')->toString());
    }
}
