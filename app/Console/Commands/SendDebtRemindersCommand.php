<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DebtReminderService;
use Illuminate\Console\Command;

final class SendDebtRemindersCommand extends Command
{
    protected $signature = 'debt:remind';

    protected $description = 'Qarzdor mijozlarga Telegram orqali eslatma yuboradi (7 kun cooldown)';

    public function handle(DebtReminderService $service): int
    {
        $result = $service->sendReminders();

        $this->info("Qarz eslatma: {$result['notified']} ta yuborildi, {$result['skipped']} ta o'tkazib yuborildi");

        return self::SUCCESS;
    }
}
