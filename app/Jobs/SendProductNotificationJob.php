<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Concerns\ReportsTelegramErrors;
use App\Models\Dealer;
use App\Telegram\BotFactory;
use App\Telegram\BotLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;
use Throwable;

/**
 * Bir mijoz a'zosiga mahsulot bildirishnomasini (yangi mahsulot yoki narx
 * o'zgarishi) yuboradi. Har qabul qiluvchi alohida job — bitta sekin/osilgan
 * curl butun batchni o'ldirmaydi (worker timeout). Matn tayyor holda keladi;
 * job faqat "Buyurtma berish" tugmasi bilan yuboradi.
 */
final class SendProductNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsTelegramErrors, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $dealerId,
        public readonly int $chatId,
        public readonly int $productId,
        public readonly string $text,
        public readonly string $locale = 'uz',
    ) {}

    public function handle(BotFactory $factory): void
    {
        $dealer = Dealer::query()->find($this->dealerId);

        if ($dealer === null) {
            return;
        }

        BotLocale::applyStored($this->locale);

        try {
            $bot = $factory->make($dealer->bot_token);

            $bot->sendMessage(
                text: $this->text,
                chat_id: $this->chatId,
                parse_mode: ParseMode::HTML,
                reply_markup: $this->keyboard($dealer),
            );
        } catch (Throwable $e) {
            if ($this->isTransientTelegramError($e) && $this->attempts() < $this->tries) {
                throw $e;
            }

            $this->handleShopMemberError($e, $this->chatId, (int) $dealer->id);
        }
    }

    private function keyboard(Dealer $dealer): InlineKeyboardMarkup
    {
        $url = rtrim((string) config('app.url'), '/')
            ."/miniapp/{$dealer->id}?tg_id={$this->chatId}&product={$this->productId}";

        return InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make(
                text: __('bot.product.order_button'),
                web_app: new WebAppInfo(url: $url),
            ),
        );
    }
}
