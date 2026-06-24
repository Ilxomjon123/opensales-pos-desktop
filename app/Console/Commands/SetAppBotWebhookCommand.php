<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\AppBotController;
use App\Telegram\AppBotFactory;
use Illuminate\Console\Command;

/**
 * OpenSales app-bot webhook'ini o'rnatadi (prod). URL: APP_URL/webhook/app.
 */
final class SetAppBotWebhookCommand extends Command
{
    protected $signature = 'opensales:bot:webhook';

    protected $description = 'Set the OpenSales login bot webhook';

    public function handle(): int
    {
        $bot = AppBotFactory::make();

        if ($bot === null) {
            $this->error('OPENSALES_BOT_TOKEN sozlanmagan (.env).');

            return self::FAILURE;
        }

        $url = $this->webhookUrl();
        $bot->setWebhook(
            url: $url,
            secret_token: AppBotController::secret(),
            drop_pending_updates: true,
            allowed_updates: ['message'],
        );

        $this->info("Webhook set: {$url}");

        return self::SUCCESS;
    }

    /**
     * Telegram -> server yo'nalishi. Server worker orqasida bo'lsa (Rossiyada
     * to'g'ridan-to'g'ri ulanib bo'lmaydi), webhook_base_url worker domeni bo'ladi.
     * Dealer webhooklari bilan bir xil mantiq (WebhookService::url()).
     */
    private function webhookUrl(): string
    {
        $base = trim((string) config('nutgram.webhook_base_url', ''));

        if ($base === '') {
            return route('telegram.webhook.app');
        }

        return rtrim($base, '/').'/webhook/app';
    }
}
