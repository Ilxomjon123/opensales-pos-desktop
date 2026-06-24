<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Concerns\ReportsTelegramErrors;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Telegram\BotFactory;
use App\Telegram\BotLocale;
use App\Telegram\Keyboards\OrderMessageKeyboard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Number;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Mijoz a'zolariga (ShopMember) zakas holati o'zgargani haqida push.
 * Har bir a'zoga alohida yuboriladi; Telegram xatosi boshqa a'zoga
 * ta'sir qilmasin — har yuborish o'z try/catch ida.
 */
final class SendOrderStatusChangeToShop implements ShouldQueue
{
    use ReportsTelegramErrors;

    public int $tries = 3;

    public function __construct(private readonly BotFactory $botFactory) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing('shop.members', 'dealer', 'items');
        $dealer = $order->dealer;

        if ($dealer === null) {
            return;
        }

        $members = $order->shop?->members()->where('is_active', true)->get() ?? collect();

        if ($members->isEmpty()) {
            return;
        }

        $bot = $this->botFactory->make($dealer->bot_token);
        $seen = [];

        foreach ($members as $member) {
            $chatId = (int) $member->telegram_id;

            if ($chatId === 0 || isset($seen[$chatId])) {
                continue;
            }

            $seen[$chatId] = true;

            BotLocale::applyStored($member->locale);

            $this->send($bot, $chatId, $this->buildMessage($order, $event->to), $order);
        }
    }

    private function send(Nutgram $bot, int $chatId, string $text, Order $order): void
    {
        try {
            $bot->sendMessage(
                text: $text,
                chat_id: $chatId,
                parse_mode: ParseMode::MARKDOWN_LEGACY,
                reply_markup: OrderMessageKeyboard::make(
                    dealerId: (int) $order->dealer_id,
                    telegramId: $chatId,
                    orderId: (int) $order->id,
                    shopId: (int) $order->shop_id,
                ),
            );
        } catch (Throwable $e) {
            $this->handleShopMemberError($e, $chatId, (int) $order->dealer_id);
        }
    }

    private function buildMessage(Order $order, OrderStatus $to): string
    {
        $shop = $order->shop;
        $emoji = match ($to) {
            OrderStatus::ASSEMBLING => '📦',
            OrderStatus::DELIVERING => '🚚',
            OrderStatus::DELIVERED => '✅',
            OrderStatus::RECEIVED => '🎉',
            OrderStatus::CANCELLED => '❌',
            default => 'ℹ️',
        };

        $lines = [
            __('bot.order.status_head', [
                'emoji' => $emoji,
                'number' => $order->displayNumber(),
                'status' => $to->label(),
            ]),
            '',
        ];

        if ($to === OrderStatus::DELIVERED) {
            $deliveredTotal = (int) ($order->delivered_total ?? 0);
            $paid = (int) ($order->paid_amount ?? 0);

            $lines[] = __('bot.order.line_delivered', ['amount' => Number::format($deliveredTotal)]);

            if ($paid > 0) {
                $lines[] = __('bot.order.line_paid', ['amount' => Number::format($paid)]);
            }

            $lines[] = __('bot.order.line_balance', ['amount' => Number::format((int) ($shop->balance ?? 0))]);
        } elseif ($to === OrderStatus::CANCELLED) {
            $lines[] = __('bot.order.line_returned', ['amount' => Number::format($order->liveItemsTotal())]);
            $lines[] = __('bot.order.line_new_balance', ['amount' => Number::format((int) ($shop->balance ?? 0))]);
        } else {
            // ASSEMBLING/DELIVERING: prepared total (picked_qty); PENDING: live items.
            $lines[] = __('bot.order.line_total', ['amount' => Number::format($order->displayTotal())]);
        }

        return implode("\n", $lines);
    }

    public function failed(OrderStatusChanged $event, Throwable $exception): void
    {
        report($exception);
    }
}
