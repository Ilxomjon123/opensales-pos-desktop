<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Telegram\AppBotFactory;
use Illuminate\Console\Command;
use SergiX44\Nutgram\RunningMode\Polling;

/**
 * OpenSales app-bot'ni long-polling rejimida ishga tushiradi (dev/localda,
 * public webhook bo'lmasa). Prod'da webhook ishlatiladi.
 */
final class RunAppBotCommand extends Command
{
    protected $signature = 'opensales:bot';

    protected $description = 'Run the OpenSales login bot in long-polling mode';

    public function handle(): int
    {
        $bot = AppBotFactory::make();

        if ($bot === null) {
            $this->error('OPENSALES_BOT_TOKEN sozlanmagan (.env).');

            return self::FAILURE;
        }

        $this->info('OpenSales bot polling... (Ctrl+C to stop)');
        $bot->setRunningMode(Polling::class);
        $bot->run();

        return self::SUCCESS;
    }
}
