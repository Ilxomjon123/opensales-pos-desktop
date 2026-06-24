<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\OrderMessageSent;
use App\Jobs\DeleteOrderMessageJob;
use App\Jobs\EditOrderMessageJob;
use App\Jobs\SendOrderMessageJob;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;

/**
 * Buyurtma ichidagi xabarlar (diller → mijoz).
 * Har bir o'zgarish Telegramda ham aks etadi (yuborish/tahrir/o'chirish).
 */
final class OrderMessageService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(Order $order, ?User $author, string $body): OrderMessage
    {
        $message = $order->messages()->create([
            'dealer_id' => $order->dealer_id,
            'author_user_id' => $author?->id,
            'body' => $body,
        ]);

        SendOrderMessageJob::dispatch($message->id);   // Telegram
        event(new OrderMessageSent($message));          // Mobil ilova (feed + FCM)

        return $message;
    }

    public function update(OrderMessage $message, string $body): OrderMessage
    {
        $message->update(['body' => $body]);

        if ($message->telegram_message_id !== null) {
            EditOrderMessageJob::dispatch($message->id);
        }

        return $message;
    }

    public function delete(OrderMessage $message): void
    {
        if ($message->telegram_message_id !== null && $message->telegram_chat_id !== null) {
            DeleteOrderMessageJob::dispatch(
                dealerId: (int) $message->dealer_id,
                chatId: (int) $message->telegram_chat_id,
                messageId: (int) $message->telegram_message_id,
            );
        }

        $this->notifications->removeOrderMessage((int) $message->id); // mobil feed
        $message->delete();
    }
}
