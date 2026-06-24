<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MarketplaceOrderCreated;
use App\Models\OrderItem;
use App\Telegram\BotFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Birja buyurtmasi yaratilganda sotuvchi (distribyutor)ga telegram xabar.
 * Botsiz distribyutorlar web "Sotuvlarim" sahifasidan ko'radi — bu yerda skip.
 */
final class SendMarketplaceOrderNotification implements ShouldQueue
{
    public function __construct(private readonly BotFactory $botFactory) {}

    public function handle(MarketplaceOrderCreated $event): void
    {
        $order = $event->order->loadMissing('dealer', 'buyerDealer', 'items');
        $seller = $order->dealer;

        if ($seller === null || ! $seller->hasBot() || $seller->telegram_chat_id === null) {
            return;
        }

        try {
            $this->botFactory->make($seller->bot_token)->sendMessage(
                text: $this->buildMessage($order),
                chat_id: $seller->telegram_chat_id,
                parse_mode: ParseMode::HTML,
            );
        } catch (Throwable) {
            // Bildirishnoma yetkazib bo'lmasa asosiy jarayon to'xtamasin.
        }
    }

    private function buildMessage($order): string
    {
        $buyer = $order->buyerDealer?->name ?? 'Diller';
        $lines = $order->items->map(
            fn (OrderItem $i): string => "• {$i->product_name} — {$i->qty} × ".number_format((float) $i->price, 0, '.', ' ')
        )->implode("\n");

        $total = number_format((int) $order->total, 0, '.', ' ');

        return "🛒 <b>Birja: yangi buyurtma #{$order->displayNumber()}</b>\n"
            ."Xaridor: <b>{$buyer}</b>\n\n"
            ."{$lines}\n\n"
            ."Jami: <b>{$total} so'm</b>";
    }
}
