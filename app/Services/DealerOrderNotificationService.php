<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\ReportsTelegramErrors;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Telegram\BotFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

/**
 * Diller chatiga yuboriladigan zakas xabari. Birinchi marta yuborilganda
 * message_id orderga yoziladi; keyingi har bir o'zgarishda (status, deliveryman,
 * tahrir) o'sha xabar Telegram editMessageText bilan yangilanadi.
 * Edit muvaffaqiyatsiz bo'lsa (masalan xabar o'chirilgan) — yangi yuboriladi.
 *
 * Strikethrough (<s>) Telegram'da faqat HTML va MarkdownV2 mode'larida ishlaydi —
 * legacy Markdown qo'llamaydi. Shuning uchun xabar HTML formatida quriladi.
 */
final class DealerOrderNotificationService
{
    use ReportsTelegramErrors;

    public function __construct(private readonly BotFactory $botFactory) {}

    public function sendOrUpdate(Order $order): void
    {
        $order->loadMissing('shop', 'items', 'dealer', 'deliveryman');
        $dealer = $order->dealer;

        if ($dealer === null || $dealer->telegram_chat_id === null) {
            return;
        }

        $currentTotal = $order->displayTotal();
        $previousTotal = $order->last_notified_total !== null ? (int) $order->last_notified_total : null;

        $text = $this->buildMessage($order, $previousTotal, $currentTotal);
        $keyboard = $this->buildKeyboard($order->shop);
        $bot = $this->botFactory->make($dealer->bot_token);

        if ($order->dealer_notification_message_id !== null) {
            try {
                $bot->editMessageText(
                    text: $text,
                    chat_id: $dealer->telegram_chat_id,
                    message_id: (int) $order->dealer_notification_message_id,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard,
                );

                $this->persistNotifiedTotal($order, $currentTotal);

                return;
            } catch (Throwable $e) {
                $message = $e->getMessage();

                // Kontent o'zgarmagan — edit shart emas, total snapshot saqlanadi.
                if (str_contains($message, 'message is not modified')) {
                    $this->persistNotifiedTotal($order, $currentTotal);

                    return;
                }

                // Transient xato (timeout, flood, 5xx) — JOB retry qilsin. Bunda
                // yangi xabar yubormaymiz, aks holda har timeout'da dublikat
                // ("Buyurtma #18" ikki marta) paydo bo'ladi. Faqat permanent
                // xatoda (xabar o'chirilgan / edit qilib bo'lmaydi) yangi yuboramiz.
                if (! $this->isPermanentEditFailure($message)) {
                    throw $e;
                }

                $this->reportUnlessBenign($e);
            }
        }

        try {
            $message = $bot->sendMessage(
                text: $text,
                chat_id: $dealer->telegram_chat_id,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard,
            );

            $messageId = $message?->message_id !== null ? (int) $message->message_id : null;

            if ($messageId !== null) {
                $order->update([
                    'dealer_notification_message_id' => $messageId,
                    'last_notified_total' => $currentTotal,
                ]);
            } else {
                $this->persistNotifiedTotal($order, $currentTotal);
            }
        } catch (Throwable $e) {
            $this->reportUnlessBenign($e);
        }
    }

    /**
     * Edit qayta urinishda ham muvaffaqiyatsiz bo'ladigan (permanent) xatomi?
     * Faqat shunda yangi xabar yuborishga o'tiladi. Transient xatolar
     * (timeout/flood/5xx) qayta tashlanadi — job retry qiladi, dublikat yo'q.
     */
    private function isPermanentEditFailure(string $message): bool
    {
        $permanent = [
            'message to edit not found',
            'message to be edited not found',
            "message can't be edited",
            'MESSAGE_ID_INVALID',
            'chat not found',
            'bot was blocked by the user',
            'bot was kicked',
            'user is deactivated',
            'message identifier is not specified',
        ];

        foreach ($permanent as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function persistNotifiedTotal(Order $order, int $currentTotal): void
    {
        if ((int) ($order->last_notified_total ?? -1) === $currentTotal) {
            return;
        }

        $order->update(['last_notified_total' => $currentTotal]);
    }

    private function buildMessage(Order $order, ?int $previousTotal, int $currentTotal): string
    {
        $shop = $order->shop;
        $statusEmoji = $this->statusEmoji($order->status);
        $orderUrl = Route::has('dealer.orders.show')
            ? route('dealer.orders.show', $order)
            : null;

        $titleSuffix = ' — '.$this->esc($order->status->label());
        $titleNumber = "Buyurtma #{$order->displayNumber()}";
        $title = $orderUrl !== null
            ? "{$statusEmoji} <a href=\"{$this->esc($orderUrl)}\">{$this->esc($titleNumber)}</a>{$titleSuffix}"
            : "{$statusEmoji} {$this->esc($titleNumber)}{$titleSuffix}";

        $lines = [
            $title,
            '',
            'Mijoz: '.$this->esc((string) ($shop?->name ?? '')),
            'Telefon: '.$this->esc($this->formatPhone($shop?->phone)),
        ];

        $addressLine = $shop !== null ? $this->formatAddress($shop) : null;
        if ($addressLine !== null) {
            $lines[] = 'Manzil: '.$this->esc($addressLine);
        }

        $deliveryman = $order->deliveryman;
        if ($deliveryman !== null) {
            $lines[] = 'Yetkazib beruvchi: '.$this->esc((string) $deliveryman->name);
        }

        $lines[] = '';

        $itemNumber = 0;

        foreach ($order->items as $item) {
            $line = $this->formatItemLine($item, $order->status);

            if ($line !== null) {
                $itemNumber++;
                $lines[] = $itemNumber.'. '.$line;
            }
        }

        $lines[] = '';

        $label = $order->delivered_at !== null && $order->delivered_total !== null
            ? 'Yetkazildi'
            : 'Jami';

        if ($previousTotal !== null && $previousTotal !== $currentTotal) {
            // Eski narx ustiga chizilgan — har status o'zgarganda jami farqi ko'rinishi uchun.
            $lines[] = sprintf(
                '<b>%s:</b> <s>%s so\'m</s> <b>%s so\'m</b>',
                $label,
                Number::format($previousTotal),
                Number::format($currentTotal),
            );
        } else {
            $lines[] = sprintf('<b>%s: %s so\'m</b>', $label, Number::format($currentTotal));
        }

        $paid = (int) ($order->paid_amount ?? 0);
        if ($paid > 0) {
            $lines[] = sprintf('To\'landi: %s so\'m', Number::format($paid));
        }

        if ($order->status === OrderStatus::CANCELLED && $order->cancellation_reason !== null) {
            $lines[] = "\nBekor qilish sababi: ".$this->esc($order->cancellation_reason);
        }

        if ($order->note !== null) {
            $lines[] = "\nIzoh: ".$this->esc($order->note);
        }

        return implode("\n", $lines);
    }

    /**
     * Shop koordinatasi bo'lsa "Kartani ko'rish" url-tugmasi qaytaradi.
     * Koordinata yo'q bo'lsa null — keyboard ko'rsatilmaydi.
     */
    private function buildKeyboard(?Shop $shop): ?InlineKeyboardMarkup
    {
        if ($shop === null) {
            return null;
        }

        $url = $this->mapUrl($shop);

        if ($url === null) {
            return null;
        }

        return InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make(text: '🗺 Kartani ko\'rish', url: $url),
        );
    }

    /**
     * Shop koordinatasidan map-provider'ga mos havola quradi.
     * useMapProvider.pointUrl bilan bir xil format.
     */
    private function mapUrl(Shop $shop): ?string
    {
        $lat = $shop->latitude;
        $lng = $shop->longitude;

        if ($lat === null || $lng === null) {
            return null;
        }

        return match ($shop->map_provider) {
            'google' => "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}",
            default => "https://yandex.uz/maps/?pt={$lng},{$lat}&z=16&l=map",
        };
    }

    private function statusEmoji(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PENDING => '🆕',
            OrderStatus::ASSEMBLING => '📦',
            OrderStatus::DELIVERING => '🚚',
            OrderStatus::DELIVERED => '✅',
            OrderStatus::RECEIVED => '🎉',
            OrderStatus::CANCELLED => '❌',
        };
    }

    /**
     * Status'ga qarab item satrini quradi. Ordered qty, prepared qty (picked),
     * delivered qty turli statuslarda asosiy ko'rinish bo'ladi; ular ordered'dan
     * farq qilsa, ordered ustiga chizilgan ko'rsatiladi.
     * Item ko'rsatilmasligi kerak bo'lsa — null qaytariladi
     * (masalan, ASSEMBLING'da picked=0 bo'lgan satrlar yashiriladi).
     */
    private function formatItemLine(OrderItem $item, OrderStatus $status): ?string
    {
        $name = $this->esc($item->displayName());
        $unit = $item->unit?->label() ?? 'dona';

        $ordered = $this->qtyParts((float) $item->qty, $item->pack_qty !== null ? (int) $item->pack_qty : 0, (float) $item->pack_size, $unit);
        $orderedSubtotal = $item->subtotal();

        $useQty = $ordered;
        $useSubtotal = $orderedSubtotal;

        if ($status === OrderStatus::ASSEMBLING || $status === OrderStatus::DELIVERING) {
            $useQty = $this->qtyParts(
                (float) ($item->picked_qty ?? 0),
                $item->picked_pack_qty !== null ? (int) $item->picked_pack_qty : 0,
                (float) $item->pack_size,
                $unit,
            );
            $useSubtotal = $item->preparedSubtotal();
        } elseif ($status === OrderStatus::DELIVERED || $status === OrderStatus::RECEIVED) {
            $useQty = $this->qtyParts(
                (float) ($item->delivered_qty ?? 0),
                $item->delivered_pack_qty !== null ? (int) $item->delivered_pack_qty : 0,
                (float) $item->pack_size,
                $unit,
            );
            $useSubtotal = $item->deliveredSubtotal();
        }

        $changed = $useQty !== $ordered || $useSubtotal !== $orderedSubtotal;

        if ($changed) {
            return sprintf(
                '[%s]: <s>%s = %s so\'m</s> → %s = %s so\'m',
                $name,
                $this->esc($ordered),
                Number::format($orderedSubtotal),
                $this->esc($useQty),
                Number::format($useSubtotal),
            );
        }

        return sprintf('[%s]: %s = %s so\'m', $name, $this->esc($useQty), Number::format($useSubtotal));
    }

    private function qtyParts(float $qty, int $packQty, float $packSize, string $unit): string
    {
        if ($packQty > 0 && $packSize > 1) {
            $loose = max(0.0, $qty - $packQty * $packSize);
            $part = sprintf('%d blok', $packQty);

            if ($loose > 0) {
                $part .= sprintf(' + %s %s', $this->trimNum($loose), $unit);
            }

            return $part;
        }

        return sprintf('%s %s', $this->trimNum($qty), $unit);
    }

    private function trimNum(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function formatAddress(Shop $shop): ?string
    {
        $parts = array_values(array_filter([
            $shop->region,
            $shop->district,
            $shop->address,
        ], fn ($v) => $v !== null && trim((string) $v) !== ''));

        if ($parts === []) {
            return null;
        }

        return implode(', ', array_map(fn ($v) => trim((string) $v), $parts));
    }

    private function formatPhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return $phone;
        }

        if (str_starts_with($digits, '998')) {
            return '+'.$digits;
        }

        if (strlen($digits) === 9) {
            return '+998'.$digits;
        }

        return '+'.$digits;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
