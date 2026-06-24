<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Concerns\ReportsTelegramErrors;
use App\Events\LowStockDetected;
use App\Telegram\BotFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

final class NotifyDealerLowStock implements ShouldQueue
{
    use ReportsTelegramErrors;

    public int $tries = 3;

    public function __construct(private readonly BotFactory $botFactory) {}

    public function handle(LowStockDetected $event): void
    {
        $product = $event->product->loadMissing('dealer');
        $dealer = $product->dealer;

        if ($dealer === null || $dealer->telegram_chat_id === null) {
            return;
        }

        $fmt = static fn (float $v): string => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        $text = sprintf(
            "⚠️ *Stok kam qoldi*\n\n*%s*\nOmborda: *%s* %s\nMinimal: %s",
            $product->name,
            $fmt((float) $product->stock),
            $product->unit->value,
            $fmt((float) $product->min_stock),
        );

        try {
            $this->botFactory->make($dealer->bot_token)->sendMessage(
                text: $text,
                chat_id: $dealer->telegram_chat_id,
                parse_mode: ParseMode::MARKDOWN_LEGACY,
            );
        } catch (Throwable $e) {
            $this->reportUnlessBenign($e);
        }
    }

    public function failed(LowStockDetected $event, Throwable $exception): void
    {
        report($exception);
    }
}
