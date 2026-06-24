<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Enums\BroadcastAudienceType;
use App\Http\Requests\BroadcastCampaignRules;
use App\Http\Requests\Concerns\ValidatesBroadcastCampaign;
use App\Models\BroadcastCampaign;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBroadcastCampaignRequest extends FormRequest
{
    use ValidatesBroadcastCampaign;

    public function authorize(): bool
    {
        $user = $this->user();
        $campaign = $this->route('campaign');

        if ($user === null || ! $user->isDealer() || ! $campaign instanceof BroadcastCampaign) {
            return false;
        }

        return (int) $campaign->dealer_id === (int) $user->dealer_id;
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
