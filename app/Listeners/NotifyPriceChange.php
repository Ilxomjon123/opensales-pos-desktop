<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProductPriceChanged;
use App\Jobs\SendProductNotificationJob;
use App\Models\ShopMember;
use App\Telegram\BotLocale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Number;
use Throwable;

/**
 * Mahsulot narxi o'zgarganda dillerning barcha faol mijoz a'zolariga
 * to'liq ma'lumot + eski/yangi narx va "Buyurtma berish" tugmasi bilan
 * matnli xabar yuboradi. Tugma mini app'ni o'sha mahsulotda ochadi —
 * rasm o'sha yerda ko'rinadi (Telegram webp'ni rasm emas sticker qiladi).
 *
 * Har qabul qiluvchiga alohida {@see SendProductNotificationJob} dispatch
 * qilinadi — bitta sekin curl butun batchni timeout'ga olib bormaydi.
 */
final class NotifyPriceChange implements ShouldQueue
{
    public int $tries = 3;

    private const TEXT_LIMIT = 4096;

    public function handle(ProductPriceChanged $event): void
    {
        $product = $event->product->loadMissing('dealer');
        $dealer = $product->dealer;

        if ($dealer === null || ! $dealer->notify_on_price_change) {
            return;
        }

        $recipients = $this->recipients($dealer->id);

        if ($recipients === []) {
            return;
        }

        // Matn har qabul qiluvchining tilida alohida quriladi.
        foreach ($recipients as $chatId => $locale) {
            BotLocale::applyStored($locale);

            SendProductNotificationJob::dispatch(
                dealerId: $dealer->id,
                chatId: $chatId,
                productId: $product->id,
                text: $this->buildText($event),
                locale: BotLocale::fromStored($locale),
            );
        }
    }

    /**
     * Diller barcha do'konlarining faol a'zolari — telegram_id bo'yicha dedup,
     * har biriga saqlangan tili bilan.
     *
     * @return array<int, string|null>
     */
    private function recipients(int $dealerId): array
    {
        $out = [];

        ShopMember::query()
            ->active()
            ->whereHas('shop', fn ($q) => $q->where('dealer_id', $dealerId))
            ->get(['telegram_id', 'locale'])
            ->each(function (ShopMember $member) use (&$out): void {
                $id = (int) $member->telegram_id;

                if ($id !== 0 && ! array_key_exists($id, $out)) {
                    $out[$id] = $member->locale;
                }
            });

        return $out;
    }

    private function buildText(ProductPriceChanged $event): string
    {
        $product = $event->product;
        $unit = $this->esc($product->unit->label());
        $name = $this->esc((string) $product->name);

        $lines = [__('bot.product.price_changed_title'), '', "<b>{$name}</b>"];

        if ($product->description !== null && $product->description !== '') {
            $lines[] = $this->esc($this->truncate((string) $product->description, 220));
        }

        $lines[] = '';
        $lines[] = __('bot.product.price_change_line', [
            'unit' => $unit,
            'old' => Number::format($event->oldPrice),
            'new' => Number::format($event->newPrice),
        ]);

        if ($event->newPackPrice !== null && $event->oldPackPrice !== null) {
            $lines[] = __('bot.product.pack_price_change_line', [
                'old' => Number::format($event->oldPackPrice),
                'new' => Number::format($event->newPackPrice),
            ]);
        }

        return $this->truncate(implode("\n", $lines), self::TEXT_LIMIT);
    }

    /**
     * Telegram HTML parse mode uchun maxsus belgilarni ekranlaydi.
     */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1).'…';
    }

    public function failed(ProductPriceChanged $event, Throwable $exception): void
    {
        report($exception);
    }
}
