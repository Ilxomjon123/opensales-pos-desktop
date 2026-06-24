<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OrderMessage;
use App\Telegram\BotFactory;
use App\Telegram\BotLocale;
use App\Telegram\Keyboards\OrderMessageKeyboard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Tahrirlangan buyurtma xabarini Telegramda ham yangilaydi.
 */
final class EditOrderMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public readonly int $orderMessageId) {}

    public function handle(BotFactory $factory): void
    {
        $message = OrderMessage::query()
            ->with(['order.dealer', 'order.member'])
            ->find($this->orderMessageId);

        if ($message === null || $message->order === null || $message->order->dealer === null) {
            return;
        }

        $chatId = (int) ($message->telegram_chat_id ?? 0);
        $messageId = (int) ($message->telegram_message_id ?? 0);

        if ($chatId <= 0 || $messageId <= 0) {
            return;
        }

        $order = $message->order;

        BotLocale::applyStored($order->member?->locale);

        try {
            $factory->make($order->dealer->bot_token)->editMessageText(
                text: $this->text($order->displayNumber(), $message->body),
                chat_id: $chatId,
                message_id: $messageId,
                reply_markup: OrderMessageKeyboard::make(
                    dealerId: (int) $order->dealer->id,
                    telegramId: $chatId,
                    orderId: (int) $order->id,
                    shopId: (int) $order->shop_id,
                    messageId: (int) $message->id,
                ),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function text(int|string|null $number, string $body): string
    {
        $number = $number !== null ? (string) $number : '';

        $head = $number !== ''
            ? __('bot.order.message_head', ['number' => $number])."\n\n"
            : '';

        return $head.$body;
    }
}
