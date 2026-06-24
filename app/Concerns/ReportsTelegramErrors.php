<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\ShopMember;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Database\Eloquent\Builder;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use Throwable;

/**
 * Telegram push yuborishda yuzaga keladigan zararsiz xatolarni
 * (foydalanuvchi botni bloklagan, chat topilmadi, akkaunt o'chirilgan)
 * Sentry'ga yubormaslik uchun. Bular kutilgan holatlar — xato emas.
 */
trait ReportsTelegramErrors
{
    /**
     * Faqat haqiqiy xatoni report qiladi; bloklash kabi
     * kutilgan Telegram javoblarini jimgina o'tkazib yuboradi.
     */
    protected function reportUnlessBenign(Throwable $e): void
    {
        // Bloklash kabi kutilgan, hamda timeout/429/5xx kabi vaqtinchalik
        // xatolar Sentry'ga yozilmaydi — faqat haqiqiy xato report qilinadi.
        if ($this->isBenignTelegramError($e) || $this->isTransientTelegramError($e)) {
            return;
        }

        report($e);
    }

    /**
     * ShopMember'ga yuborishda xato: foydalanuvchi bloklagan bo'lsa
     * uni shu diller doirasida `blocked_at` bilan belgilaydi (qayta urinish
     * foyda bermaydi); transient xato jimgina o'tkaziladi; aks holda report.
     */
    protected function handleShopMemberError(Throwable $e, int $telegramId, int $dealerId): void
    {
        if (! $this->isBenignTelegramError($e)) {
            $this->reportUnlessBenign($e);

            return;
        }

        ShopMember::query()
            ->where('telegram_id', $telegramId)
            ->whereHas('shop', fn (Builder $q) => $q->where('dealer_id', $dealerId))
            ->whereNull('blocked_at')
            ->update(['blocked_at' => now()]);
    }

    /**
     * Telegram tomonidan qaytarilgan, qayta urinish foyda bermaydigan
     * yetkazib bo'lmaslik holatlarini aniqlaydi.
     */
    protected function isBenignTelegramError(Throwable $e): bool
    {
        if (! $e instanceof TelegramException) {
            return false;
        }

        // 403 Forbidden — bot bloklangan yoki guruhdan chiqarilgan.
        if ($e->getCode() === 403) {
            return true;
        }

        $message = strtolower($e->getMessage());

        foreach ([
            'bot was blocked by the user',
            'user is deactivated',
            'chat not found',
            'bot can\'t initiate conversation',
            'peer_id_invalid',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vaqtinchalik (transient) xato: tarmoq timeout/uzilish, rate limit (429)
     * yoki Telegram 5xx. Bularda qayta urinish (queue retry) mantiqiy —
     * doimiy xato emas, Sentry'ga har urinishda yozish shart emas.
     */
    protected function isTransientTelegramError(Throwable $e): bool
    {
        if ($e instanceof ConnectException) {
            return true;
        }

        // Nutgram ConnectException'ni redact qilib qayta tashlaydi (previous'da asl).
        if ($e->getPrevious() instanceof ConnectException) {
            return true;
        }

        if ($e instanceof TelegramException) {
            $code = $e->getCode();

            return $code === 429 || ($code >= 500 && $code <= 599);
        }

        return false;
    }
}
