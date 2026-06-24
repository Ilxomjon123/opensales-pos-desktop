<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Concerns\ReportsTelegramErrors;
use App\Events\OrderCreated;
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
 * Mijoz a'zolariga yangi buyurtma tasdiqi.
 * Buyurtmani yaratgan a'zo (member_id) aniq belgilangan — unga tasdiq
 * yuboriladi; qolgan a'zolar OrderCreated-ning dealer notifikatsiyasi
 * emas, o'zlariga tegishli mini-appdan status tarixini ko'radilar.
 */
final class SendOrderConfirmationToShop implements ShouldQueue
{
    use ReportsTelegramErrors;

    public int $tries = 3;

    public function __construct(private readonly BotFactory $botFactory) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->order->loadMissing('shop.members', 'dealer', 'items');
        $shop = $order->shop;
        $dealer = $order->dealer;

        if ($shop === null || $dealer === null) {
            return;
        }

        // Faqat mijozning faol a'zolari — telegram_id bo'yicha aniq dedup.
        // Buyurtmani yaratgan a'zo (member_id) ham shu yerda bor — qo'shimcha
        // push() qilish shart emas, aks holda 2 marta yuboriladi. Matn har
        // a'zoning tilida alohida quriladi.
        $seen = [];
        $bot = $this->botFactory->make($dealer->bot_token);

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

            $text = __('bot.order.confirmed', [
                'number' => $order->displayNumber(),
                'total' => Number::format($order->liveItemsTotal()),
                'balance' => Number::format((int) $shop->balance),
            ]);

            $this->send($bot, $chatId, $text, $order);
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

    public function failed(OrderCreated $event, Throwable $exception): void
    {
        report($exception);
    }
}
