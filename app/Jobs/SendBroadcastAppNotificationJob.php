<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BroadcastAudienceType;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\ShopMember;
use App\Services\Broadcast\BroadcastAudienceResolver;
use App\Services\Broadcast\BroadcastRenderer;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Ommaviy (darhol) xabarning mobil ilova mijozlariga feed + FCM yetkazilishi.
 * Telegramdan mustaqil; web so'rovini bloklab qo'ymaslik uchun queue da bajariladi.
 *
 * @phpstan-param array<string, mixed> $config
 */
final class SendBroadcastAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly int $dealerId,
        public readonly string $audienceType,
        public readonly array $config,
        public readonly string $message,
        public readonly string $title,
    ) {}

    public function handle(
        BroadcastAudienceResolver $audience,
        BroadcastRenderer $renderer,
        NotificationService $notifications,
    ): void {
        $dealer = Dealer::query()->find($this->dealerId);

        if ($dealer === null) {
            return;
        }

        $draft = new BroadcastCampaign([
            'audience_type' => BroadcastAudienceType::from($this->audienceType),
            'audience_config' => $this->config,
        ]);
        $draft->dealer_id = $this->dealerId;
        $draft->message_text = $this->message;

        $notifications->broadcast(
            $this->title,
            $audience->mobileMembers($draft),
            fn (ShopMember $m): string => $renderer->render($draft, $m->shop, $dealer, $m),
        );
    }
}
