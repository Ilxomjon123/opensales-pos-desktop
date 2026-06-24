<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Dealer;
use App\Telegram\BotFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * O'chirilgan buyurtma xabarini Telegramdan ham o'chiradi.
 * Primitive parametrlar — OrderMessage qatori allaqachon o'chirilgan bo'ladi.
 */
final class DeleteOrderMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public readonly int $dealerId,
        public readonly int $chatId,
        public readonly int $messageId,
    ) {}

    public function handle(BotFactory $factory): void
    {
        $dealer = Dealer::query()->find($this->dealerId);

        if ($dealer === null || $this->chatId <= 0 || $this->messageId <= 0) {
            return;
        }

        try {
            $factory->make($dealer->bot_token)->deleteMessage(
                chat_id: $this->chatId,
                message_id: $this->messageId,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
