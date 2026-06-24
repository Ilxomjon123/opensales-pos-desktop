<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\TelegramLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Telegram orqali kirish (SMS'siz) — app tomon endpointlari.
 */
final class MobileTelegramAuthController extends Controller
{
    public function __construct(private readonly TelegramLoginService $login) {}

    public function start(): JsonResponse
    {
        $token = $this->login->start(app()->getLocale());

        return $this->botUrlResponse($token, 'auth_');
    }

    /**
     * Allaqachon kirgan foydalanuvchi uchun — telegramni ULASH tokeni.
     * Bot telefon so'ramaydi, to'g'ridan-to'g'ri shu customer'ga bog'laydi.
     */
    public function linkStart(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $token = $this->login->startLink($customer->id, app()->getLocale());

        return $this->botUrlResponse($token, 'link_');
    }

    private function botUrlResponse(string $token, string $prefix): JsonResponse
    {
        $username = config('services.opensales_bot.username');

        return response()->json([
            'token' => $token,
            'bot_username' => $username,
            'bot_url' => $username !== null ? "https://t.me/{$username}?start={$prefix}{$token}" : null,
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);

        return response()->json($this->login->poll($data['token']));
    }
}
