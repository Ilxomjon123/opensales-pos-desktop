<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\WebhookServiceInterface;
use App\Jobs\SetupWebhookJob;
use App\Models\Dealer;
use Illuminate\Console\Command;

final class WebhookSetupCommand extends Command
{
    protected $signature = 'webhook:setup
        {--dealer= : Faqat bitta dealer uchun (ID)}
        {--remove : Webhook larni o\'chirish}
        {--sync : Queue\'siz, darhol bajarish (eski xulq)}';

    protected $description = 'Barcha faol dillerlar uchun Telegram webhook larni ro\'yxatga olish';

    public function handle(WebhookServiceInterface $webhookService): int
    {
        $remove = (bool) $this->option('remove');
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
            '%s %d ta diller uchun webhook %s...',
            $remove ? '🗑' : '🔗',
            $dealers->count(),
            $remove ? 'o\'chirilmoqda' : 'o\'rnatilmoqda',
        ));

        // Default: har diller alohida queue job. register() ~6 ta ketma-ket
        // Telegram API call qiladi — N ta dillerni sync ishlash sekin.
        if (! $this->option('sync')) {
            foreach ($dealers as $dealer) {
                SetupWebhookJob::dispatch($dealer->id, $remove);
                $this->line("  ↪ {$dealer->name} (@{$dealer->bot_username}) navbatga qo'shildi");
            }

            $this->newLine();
            $this->info("{$dealers->count()} ta job navbatga qo'shildi. Worker ishlayotganiga ishonch hosil qiling: php artisan queue:work");

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        foreach ($dealers as $dealer) {
            $result = $remove
                ? $webhookService->remove($dealer)
                : $webhookService->register($dealer);

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
