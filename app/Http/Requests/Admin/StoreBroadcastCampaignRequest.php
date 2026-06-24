<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BroadcastAudienceType;
use App\Http\Requests\BroadcastCampaignRules;
use App\Http\Requests\Concerns\ValidatesBroadcastCampaign;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBroadcastCampaignRequest extends FormRequest
{
    use ValidatesBroadcastCampaign;

    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $rules = BroadcastCampaignRules::rules([
            BroadcastAudienceType::PLATFORM_DEALERS,
            BroadcastAudienceType::PLATFORM_SHOP_MEMBERS,
            BroadcastAudienceType::ALL_ACTIVE,
            BroadcastAudienceType::SELECTED_SHOPS,
            BroadcastAudienceType::FILTER,
        ]);

        $rules['dealer_id'] = ['nullable', 'integer', 'exists:dealers,id'];

        return $rules;
    }
}
