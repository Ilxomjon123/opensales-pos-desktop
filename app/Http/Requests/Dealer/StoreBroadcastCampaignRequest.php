<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\BroadcastAudienceType;
use App\Http\Requests\BroadcastCampaignRules;
use App\Http\Requests\Concerns\ValidatesBroadcastCampaign;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBroadcastCampaignRequest extends FormRequest
{
    use ValidatesBroadcastCampaign;

    public function authorize(): bool
    {
        return $this->user()?->isDealer() ?? false;
    }

    public function rules(): array
    {
        return BroadcastCampaignRules::rules([
            BroadcastAudienceType::ALL_ACTIVE,
            BroadcastAudienceType::SELECTED_SHOPS,
            BroadcastAudienceType::FILTER,
        ]);
    }
}
