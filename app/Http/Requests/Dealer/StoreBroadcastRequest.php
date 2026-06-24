<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastMediaType;
use App\Http\Requests\BroadcastCampaignRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDealer() ?? false;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:4000'],

            'media' => ['nullable', 'file', 'max:20480'],
            'media_type' => ['nullable', Rule::in(array_map(fn (BroadcastMediaType $t): string => $t->value, BroadcastMediaType::cases()))],

            'buttons' => ['nullable', 'array', 'max:5'],
            'buttons.*' => ['array', 'min:1', 'max:3'],
            'buttons.*.*.text' => ['required_with:buttons.*', 'string', 'max:64'],
            'buttons.*.*.url' => ['required_with:buttons.*', 'string', 'url', 'max:256'],

            ...BroadcastCampaignRules::audienceRules([
                BroadcastAudienceType::ALL_ACTIVE,
                BroadcastAudienceType::SELECTED_SHOPS,
                BroadcastAudienceType::FILTER,
            ]),
        ];
    }
}
