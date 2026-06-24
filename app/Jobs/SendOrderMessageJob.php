<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Concerns\ReportsTelegramErrors;
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
 * Buyurtma xabarini mijozga (buyurtma egasiga) Telegram orqali yuboradi
 * va edit/delete uchun telegram_message_id ni saqlaydi.
 */
final class SendOrderMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsTelegramErrors, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public readonly int $orderMessageId) {}

    public function handle(BotFactory $factory): void
    {
        $message = OrderMessage::query()
            ->with(['order.member', 'order.dealer'])
            ->find($this->orderMessageId);

        if ($message === null || $message->order === null) {
            return;
        }

        $order = $message->order;
        $dealer = $order->dealer;
        $member = $order->member;
        $chatId = (int) ($member->telegram_id ?? 0);

        // Mijoz Telegramga ulanmagan bo'lsa — xabar faqat panel/mini appda qoladi.
        if ($dealer === null || $chatId <= 0) {
            return;
        }

        BotLocale::applyStored($member?->locale);

        try {
            $sent = $factory->make($dealer->bot_token)->sendMessage(
                text: $this->text($order->displayNumber(), $message->body),
                chat_id: $chatId,
                reply_markup: OrderMessageKeyboard::make(
                    dealerId: (int) $dealer->id,
                    telegramId: $chatId,
                    orderId: (int) $order->id,
                    shopId: (int) $order->shop_id,
                    messageId: (int) $message->id,
                ),
            );

            $message->forceFill([
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $sent?->message_id,
            ])->saveQuietly();
        } catch (Throwable $e) {
            if ($this->isTransientTelegramError($e) && $this->attempts() < $this->tries) {
                throw $e;
            }

            $this->handleShopMemberError($e, $chatId, (int) $dealer->id);
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
