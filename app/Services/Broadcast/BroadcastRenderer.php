<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use Illuminate\Support\Carbon;

/**
 * Template placeholderlarni real qiymatlar bilan almashtirish.
 * Qo'llab-quvvatlanadi: {shop_name}, {shop_phone}, {balance},
 * {contact_person}, {member_name}, {dealer_name}, {date}, {time}, {today}.
 */
final class BroadcastRenderer
{
    public function render(
        BroadcastCampaign $campaign,
        ?Shop $shop = null,
        ?Dealer $dealer = null,
        ?ShopMember $member = null,
    ): string {
        $now = Carbon::now($campaign->timezone ?: 'Asia/Tashkent');
        $resolvedDealer = $dealer ?? $shop?->dealer ?? $campaign->dealer;

        $vars = [
            '{shop_name}' => $shop?->name ?? '',
            '{shop_phone}' => $shop?->phone ?? '',
            '{balance}' => $shop !== null ? number_format((float) $shop->balance, 0, '.', ' ') : '',
            '{contact_person}' => $shop?->contact_person ?? '',
            '{member_name}' => $member?->name ?? '',
            '{dealer_name}' => $resolvedDealer?->name ?? '',
            '{date}' => $now->format('d.m.Y'),
            '{today}' => $now->format('d.m.Y'),
            '{time}' => $now->format('H:i'),
        ];

        return strtr($campaign->message_text, $vars);
    }
}
