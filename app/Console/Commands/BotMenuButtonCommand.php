<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\WebhookServiceInterface;
use App\Models\Dealer;
use Illuminate\Console\Command;

final class BotMenuButtonCommand extends Command
{
    protected $signature = 'bot:menu-button
        {--dealer= : Faqat bitta dealer uchun (ID)}
        {--reset : Menu button ni standart holatga qaytarish}';

    protected $description = 'Barcha faol dillerlarning Telegram bot menu button ini Web App ga sozlash';

    public function handle(WebhookServiceInterface $webhookService): int
    {
        $reset = (bool) $this->option('reset');
        $dealerId = $this->option('dealer');

        $query = Dealer::query();

        if ($dealerId !== null) {
            $query->where('id', (int) $dealerId);
        } else {
            $query->active();
        }

        $dealers = $query->get();

        if ($dealers->isEmpty()) {
            $this->warn('Faol dillerlar topilmadi.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '🛍 %d ta diller uchun menu button %s...',
            $dealers->count(),
            $reset ? 'standart holatga qaytarilmoqda' : 'sozlanmoqda',
        ));

        $success = 0;
        $failed = 0;

        foreach ($dealers as $dealer) {
            $result = $reset
                ? $webhookService->resetMenuButton($dealer)
                : $webhookService->setMenuButton($dealer);

            if ($result) {
                $this->line("  ✓ {$dealer->name} (@{$dealer->bot_username})");
                $success++;
            } else {
                $this->error("  ✗ {$dealer->name} (@{$dealer->bot_username})");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Natija: {$success} muvaffaqiyatli, {$failed} xato");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
