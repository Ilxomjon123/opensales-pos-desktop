<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Dealer;
use App\Models\ShopMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telegram Mini App (WebApp) initData ni tekshiradi.
 *
 * initData header yoki query param orqali qabul qilinadi:
 * - Header: X-Telegram-Init-Data
 * - Query: ?_auth=...
 */
final class ValidateTelegramWebApp
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Dealer|null $dealer */
        $dealer = $request->route('dealer');
        abort_if($dealer === null || ! $dealer->is_active, 404);

        // 1. initData (HMAC bilan — InlineKeyboard WebApp)
        $initData = $request->header('X-Telegram-Init-Data', '')
            ?: $request->input('_auth', '');

        if ($initData !== '' && $initData !== null) {
            $telegramId = $this->extractUserId($initData);

            if ($telegramId === null) {
                abort(403, 'initData dan user.id topilmadi');
            }

            // Production da hash tekshiruv
            if (app()->isProduction() && ! $this->validateHash($initData, $dealer->bot_token)) {
                abort(403, 'initData yaroqsiz');
            }

            return $this->bindShopAndContinue($request, $dealer, $telegramId, $next);
        }

        // 2. _tg_id (ReplyKeyboard WebApp — initData bo'sh, faqat user.id bor)
        $tgId = $request->input('_tg_id');
        if ($tgId !== null && (int) $tgId > 0) {
            return $this->bindShopAndContinue($request, $dealer, (int) $tgId, $next);
        }

        // 3. Dev mode fallback
        if (! app()->isProduction() && $request->has('dev_telegram_id')) {
            return $this->bindShopAndContinue($request, $dealer, (int) $request->input('dev_telegram_id'), $next);
        }

        abort(401, 'Telegram initData kerak');

        return $this->bindShopAndContinue($request, $dealer, $telegramId, $next);
    }

    private function bindShopAndContinue(Request $request, Dealer $dealer, int $telegramId, Closure $next): Response
    {
        $baseQuery = ShopMember::query()
            ->forTelegram($telegramId)
            ->active()
            ->whereHas('shop', fn ($q) => $q->where('dealer_id', $dealer->id))
            ->with('shop');

        // Foydalanuvchi aniq mijozni tanlagan bo'lsa — shu orqali topamiz
        $desiredShopId = (int) ($request->input('shop_id') ?? $request->header('X-Shop-Id') ?? 0);

        $member = null;
        if ($desiredShopId > 0) {
            $member = (clone $baseQuery)->where('shop_id', $desiredShopId)->first();
        }

        // Fallback — birinchi mijoz (agar user bitta mijoz a'zosi bo'lsa — avtomatik)
        if ($member === null) {
            $member = $baseQuery->first();
        }

        $request->attributes->set('dealer', $dealer);
        $request->attributes->set('member', $member);
        $request->attributes->set('shop', $member?->shop);
        $request->attributes->set('telegram_id', $telegramId);

        return $next($request);
    }

    private function extractUserId(string $rawInitData): ?int
    {
        foreach (explode('&', $rawInitData) as $part) {
            $eqPos = strpos($part, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = substr($part, 0, $eqPos);

            if ($key === 'user') {
                $value = urldecode(substr($part, $eqPos + 1));
                $userData = json_decode($value, true);

                return isset($userData['id']) ? (int) $userData['id'] : null;
            }
        }

        return null;
    }

    private function validateHash(string $rawInitData, string $botToken): bool
    {
        $pairs = [];
        $hash = null;

        foreach (explode('&', $rawInitData) as $part) {
            $eqPos = strpos($part, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = substr($part, 0, $eqPos);
            $value = urldecode(substr($part, $eqPos + 1));

            if ($key === 'hash') {
                $hash = $value;
            } else {
                $pairs[] = "{$key}={$value}";
            }
        }

        if ($hash === null || $pairs === []) {
            return false;
        }

        sort($pairs);
        $dataCheckString = implode("\n", $pairs);

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', binary: true);
        $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, binary: true));

        return hash_equals($calculatedHash, $hash);
    }
}
