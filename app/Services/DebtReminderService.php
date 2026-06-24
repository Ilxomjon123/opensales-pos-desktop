<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendBroadcastMessageJob;
use App\Models\Shop;
use App\Telegram\BotLocale;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Number;

/**
 * Qarzdor mijozlar a'zolariga Telegram orqali eslatma.
 * Har mijoz uchun 7 kun cooldown — mijoz vakili bir xil xabarni spam ko'rinmasligi uchun.
 */
final class DebtReminderService
{
    private const COOLDOWN_SECONDS = 604_800; // 7 kun

    private const MIN_DEBT_AMOUNT = 1_000; // 1000 so'm dan kam bo'lsa eslatmaymiz

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * @return array{notified: int, skipped: int}
     */
    public function sendReminders(): array
    {
        $notified = 0;
        $skipped = 0;

        Shop::query()
            ->where('balance', '<=', -self::MIN_DEBT_AMOUNT)
            ->where('is_active', true)
            ->with(['dealer', 'members' => fn ($q) => $q->where('is_active', true)])
            ->chunkById(100, function ($shops) use (&$notified, &$skipped): void {
                foreach ($shops as $shop) {
                    if ($shop->dealer === null || $shop->members->isEmpty()) {
                        $skipped++;

                        continue;
                    }

                    $key = $this->cooldownKey($shop->id);

                    if ($this->cache->has($key)) {
                        $skipped++;

                        continue;
                    }

                    $this->cache->put($key, true, self::COOLDOWN_SECONDS);

                    foreach ($shop->members as $member) {
                        BotLocale::applyStored($member->locale);

                        SendBroadcastMessageJob::dispatch(
                            dealerId: $shop->dealer->id,
                            chatId: (int) $member->telegram_id,
                            message: $this->buildMessage(
                                (int) $shop->balance,
                                (string) $shop->dealer->name,
                                (string) $shop->name,
                            ),
                        );
                    }

                    $notified++;
                }
            });

        return ['notified' => $notified, 'skipped' => $skipped];
    }

    private function buildMessage(int $balance, string $dealerName, string $shopName): string
    {
        $amount = abs($balance);

        return __('bot.debt.reminder', [
            'shop' => $shopName,
            'dealer' => $dealerName,
            'amount' => Number::format($amount),
        ]);
    }

    private function cooldownKey(int $shopId): string
    {
        return "debt_reminder_cooldown:{$shopId}";
    }
}
