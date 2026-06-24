<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BotHealthService;
use Illuminate\Console\Command;

final class CheckBotHealthCommand extends Command
{
    protected $signature = 'bot:health-check';

    protected $description = 'Barcha faol dillerlar uchun getWebhookInfo natijalarini yangilaydi';

    public function handle(BotHealthService $service): int
    {
        $result = $service->checkAll();

        $this->info(sprintf(
            'Bot health: %d ta tekshirildi, %d ok, %d xatolik',
            $result['checked'],
            $result['ok'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
