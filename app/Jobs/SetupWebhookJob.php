<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WebhookServiceInterface;
use App\Models\Dealer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Bitta diller uchun webhook o'rnatadi yoki o'chiradi. Har diller alohida job —
 * register() ~6 ta ketma-ket Telegram API call qiladi (getMe, setWebhook,
 * setChatMenuButton, setMyName/ShortDescription/Description), shu bois N ta
 * dillerni ketma-ket sync ishlash sekin. Queue parallel/non-blocking bajaradi.
 */
final class SetupWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $dealerId,
        public readonly bool $remove = false,
    ) {}

    public function handle(WebhookServiceInterface $webhookService): void
    {
        $dealer = Dealer::query()->find($this->dealerId);

        if ($dealer === null) {
            return;
        }

        $this->remove
            ? $webhookService->remove($dealer)
            : $webhookService->register($dealer);
    }
}
