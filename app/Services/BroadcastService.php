<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BroadcastAudienceType;
use App\Jobs\SendBroadcastAppNotificationJob;
use App\Jobs\SendBroadcastMessageJob;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Services\Broadcast\BroadcastAudienceResolver;
use Illuminate\Support\Carbon;

/**
 * Diller botidan mijoz a'zolariga ommaviy xabar yuborish.
 * Auditoriya rejalashtirilgan kampaniyalardagi bilan bir xil resolver orqali
 * hisoblanadi (barcha faol / tanlangan / filtr). Har a'zo uchun alohida job;
 * queue serial ishlaydi → Telegram rate limit ga zid kelmaydi.
 */
final class BroadcastService
{
    public function __construct(private readonly BroadcastAudienceResolver $audience) {}

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, mixed>  $buttons  inline tugmalar (qatorlar × tugmalar)
     */
    public function dispatch(
        Dealer $dealer,
        string $message,
        BroadcastAudienceType $type,
        array $config = [],
        array $buttons = [],
        ?string $mediaPath = null,
        ?string $mediaType = null,
    ): int {
        $count = 0;
        $now = Carbon::now();
        $draft = $this->draft($dealer, $type, $config);

        foreach ($this->audience->resolve($draft) as $recipient) {
            SendBroadcastMessageJob::dispatch(
                dealerId: $dealer->id,
                chatId: (int) $recipient['chat_id'],
                message: $message,
                shopId: isset($recipient['shop_id']) ? (int) $recipient['shop_id'] : null,
                buttons: $buttons,
                mediaPath: $mediaPath,
                mediaType: $mediaType,
            )->delay($now->copy()->addMilliseconds(50 * $count));

            $count++;
        }

        // Mobil ilova mijozlariga (feed + FCM) — Telegramdan mustaqil, queue da.
        SendBroadcastAppNotificationJob::dispatch(
            dealerId: $dealer->id,
            audienceType: $type->value,
            config: $config,
            message: $message,
            title: $dealer->name,
        );

        return $count;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function recipientCount(Dealer $dealer, BroadcastAudienceType $type, array $config = []): int
    {
        return $this->audience->count($this->draft($dealer, $type, $config));
    }

    /**
     * Saqlanmaydigan (transient) kampaniya — resolver faqat dealer_id,
     * audience_type va audience_config ni o'qiydi.
     *
     * @param  array<string, mixed>  $config
     */
    private function draft(Dealer $dealer, BroadcastAudienceType $type, array $config): BroadcastCampaign
    {
        $campaign = new BroadcastCampaign([
            'audience_type' => $type,
            'audience_config' => $config,
        ]);

        $campaign->dealer_id = $dealer->id;

        return $campaign;
    }
}
