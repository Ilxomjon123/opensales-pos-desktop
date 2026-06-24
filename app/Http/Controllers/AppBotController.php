<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Telegram\AppBotFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SergiX44\Nutgram\RunningMode\Webhook;

/**
 * OpenSales app-bot webhook (prod). Localda esa `php artisan opensales:bot` (polling).
 */
final class AppBotController extends Controller
{
    public function handle(Request $request): Response
    {
        if (app()->isProduction()
            && $request->header('X-Telegram-Bot-Api-Secret-Token') !== self::secret()) {
            abort(403);
        }

        $bot = AppBotFactory::make();

        if ($bot !== null) {
            $bot->setRunningMode(Webhook::class);
            $bot->run();
        }

        return response('', 200);
    }

    public static function secret(): string
    {
        return md5((string) config('app.key').'opensales');
    }
}
