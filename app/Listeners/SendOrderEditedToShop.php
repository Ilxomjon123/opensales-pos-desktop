<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Concerns\ReportsTelegramErrors;
use App\Events\OrderEdited;
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
 * Mijoz a'zolariga buyurtma tahrirlangani haqida push.
 * Har bir faol a'zoga alohida yuboriladi; bitta a'zo xatosi qolganlariga
 * ta'sir qilmasin — har yuborish o'z try/catch ida.
 */
final class SendOrderEditedToShop implements ShouldQueue
{
    use ReportsTelegramErrors;

    public int $tries = 3;

    public function __construct(private readonly BotFactory $botFactory) {}

    public function handle(OrderEdited $event): void
    {
        $order = $event->order->loadMissing('shop.members', 'dealer');
        $dealer = $order->dealer;
        $shop = $order->shop;

        if ($dealer === null || $shop === null) {
            return;
        }

        $bot = $this->botFactory->make($dealer->bot_token);
        $seen = [];

        foreach ($shop->members as $member) {
            if (! $member->is_active) {
                continue;
            }

            $chatId = (int) $member->telegram_id;

            if ($chatId === 0 || isset($seen[$chatId])) {
                continue;
            }

            $seen[$chatId] = true;

            BotLocale::applyStored($member->locale);

            $this->send($bot, $chatId, $this->buildMessage($order), $order);
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

    private function buildMessage(Order $order): string
    {
        $deliveredTotal = (int) ($order->delivered_total ?? $order->total);
        $paid = (int) ($order->paid_amount ?? 0);
        $discount = (int) ($order->discount ?? 0);
        $balance = (int) ($order->shop->balance ?? 0);

        $lines = [
            __('bot.order.edited_head', ['number' => $order->displayNumber()]),
            '',
            __('bot.order.line_delivered', ['amount' => Number::format($deliveredTotal)]),
        ];

        if ($discount > 0) {
            $lines[] = __('bot.order.line_discount', ['amount' => Number::format($discount)]);
        }

        if ($paid > 0) {
            $lines[] = __('bot.order.line_paid', ['amount' => Number::format($paid)]);
        }

        $lines[] = __('bot.order.line_balance', ['amount' => Number::format($balance)]);

        return implode("\n", $lines);
    }

    public function failed(OrderEdited $event, Throwable $exception): void
    {
        report($exception);
    }
}
