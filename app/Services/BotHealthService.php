<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dealer;
use App\Telegram\BotFactory;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Telegram bot webhook holatini tekshiradi (getWebhookInfo) va
 * har diller uchun natijani DB ga yozadi. Xatoliklar avtomatik dashboard da ko'rinadi.
 */
final class BotHealthService
{
    public function __construct(private readonly BotFactory $botFactory) {}

    /**
     * @return array{ok: bool, pending: int, error: string|null, error_at: ?CarbonImmutable, url: string|null}
     */
    public function check(Dealer $dealer): array
    {
        try {
            $bot = $this->botFactory->make($dealer->bot_token);
            $info = $bot->getWebhookInfo();

            $errorAt = $info?->last_error_date !== null
                ? CarbonImmutable::createFromTimestamp($info->last_error_date)
                : null;

            $dealer->update([
                'webhook_checked_at' => now(),
                'webhook_pending_updates' => $info?->pending_update_count ?? 0,
                'webhook_last_error_message' => $info?->last_error_message,
                'webhook_last_error_at' => $errorAt,
                'webhook_url' => $info?->url !== '' ? $info?->url : null,
            ]);

            return [
                'ok' => $info !== null && $info->url !== '',
                'pending' => (int) ($info?->pending_update_count ?? 0),
                'error' => $info?->last_error_message,
                'error_at' => $errorAt,
                'url' => $info?->url !== '' ? $info?->url : null,
            ];
        } catch (Throwable $e) {
            report($e);

            $dealer->update([
                'webhook_checked_at' => now(),
                'webhook_last_error_message' => substr($e->getMessage(), 0, 1000),
                'webhook_last_error_at' => now(),
            ]);

            return [
                'ok' => false,
                'pending' => 0,
                'error' => $e->getMessage(),
                'error_at' => CarbonImmutable::now(),
                'url' => null,
            ];
        }
    }

    /**
     * @return array{checked: int, ok: int, failed: int}
     */
    public function checkAll(): array
    {
        $checked = $ok = $failed = 0;

        Dealer::query()
            ->active()
            ->whereNotNull('bot_token')
            ->chunkById(50, function ($dealers) use (&$checked, &$ok, &$failed): void {
                foreach ($dealers as $dealer) {
                    $result = $this->check($dealer);
                    $checked++;

                    if ($result['ok']) {
                        $ok++;
                    } else {
                        $failed++;
                    }
                }
            });

        return ['checked' => $checked, 'ok' => $ok, 'failed' => $failed];
    }
}
