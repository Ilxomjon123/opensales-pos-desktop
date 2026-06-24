<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Telegram\BotFactory;
use Illuminate\Http\Response;
use Sentry\State\Scope;
use Throwable;

use function Sentry\configureScope;

final class BotController extends Controller
{
    public function __construct(private readonly BotFactory $botFactory) {}

    public function handle(Dealer $dealer): Response
    {
        abort_if(! $dealer->is_active, 404);

        configureScope(function (Scope $scope) use ($dealer): void {
            $scope->setTag('dealer_id', (string) $dealer->id);
            $scope->setTag('bot_username', (string) $dealer->bot_username);
        });

        try {
            $bot = $this->botFactory->forDealer($dealer);
            $bot->run();
        } catch (Throwable $e) {
            report($e);
        }

        // Telegram ga doim 200 qaytaramiz — aks holda retry qiladi
        return new Response('', 200);
    }
}
