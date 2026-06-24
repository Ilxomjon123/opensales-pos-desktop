<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastAudienceType: string
{
    case ALL_ACTIVE = 'all_active';
    case SELECTED_SHOPS = 'selected_shops';
    case FILTER = 'filter';

    /**
     * Super admin uchun: barcha dillerlar yoki barcha mijoz a'zolari.
     */
    case PLATFORM_DEALERS = 'platform_dealers';
    case PLATFORM_SHOP_MEMBERS = 'platform_shop_members';

    public function label(): string
    {
        return (string) __('enums.BroadcastAudienceType.'.$this->value);
    }
}
