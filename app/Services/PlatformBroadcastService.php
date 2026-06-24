<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BroadcastAudienceType;
use App\Jobs\SendBroadcastMessageJob;
use App\Models\BroadcastCampaign;
use App\Services\Broadcast\BroadcastAudienceResolver;
use Illuminate\Support\Carbon;

/**
 * Super admin — barcha platformaga ommaviy xabar yuborish.
 * Auditoriya rejalashtirilgan kampaniyalardagi resolver orqali hisoblanadi:
 *   - platform_dealers: har diller uchun uning o'z botidan telegram_chat_id ga
 *   - platform_shop_members: har mijoz a'zosiga ularning dillerining boti orqali
 * Ikkalasi ham dealer_ids / saldo / hudud kabi filtrlarni qo'llab-quvvatlaydi.
 */
final class PlatformBroadcastService
{
    public function __construct(private readonly BroadcastAudienceResolver $audience) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function dispatch(string $message, BroadcastAudienceType $type, array $config = []): int
    {
        $count = 0;
        $now = Carbon::now();

        foreach ($this->audience->resolve($this->draft($type, $config)) as $recipient) {
            SendBroadcastMessageJob::dispatch(
                dealerId: (int) $recipient['dealer_id'],
                chatId: (int) $recipient['chat_id'],
                message: $message,
            )->delay($now->copy()->addMilliseconds(50 * $count));

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function recipientCount(BroadcastAudienceType $type, array $config = []): int
    {
        return $this->audience->count($this->draft($type, $config));
    }

    /**
     * Platforma darajasidagi transient kampaniya (dealer_id = null).
     *
     * @param  array<string, mixed>  $config
     */
    private function draft(BroadcastAudienceType $type, array $config): BroadcastCampaign
    {
        $campaign = new BroadcastCampaign([
            'audience_type' => $type,
            'audience_config' => $config,
        ]);

        $campaign->dealer_id = null;

        return $campaign;
    }
}
