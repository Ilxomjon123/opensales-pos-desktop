<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Sms\EskizClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS yuborish. Drayver config('services.sms.driver'):
 *  - log   — kodni log ga yozadi (dev).
 *  - eskiz — Eskiz.uz orqali yuboradi (prod).
 */
final class SmsService
{
    public function __construct(private readonly EskizClient $eskiz) {}

    public function send(string $phone, string $text): void
    {
        $driver = (string) config('services.sms.driver', 'log');

        if ($driver === 'eskiz') {
            try {
                $this->eskiz->send($phone, $text);
            } catch (Throwable $e) {
                report($e);
                Log::warning('Eskiz SMS xato', ['phone' => $phone, 'error' => $e->getMessage()]);
            }

            return;
        }

        // log (dev): kodni log ga yozamiz.
        Log::info('SMS', ['phone' => $phone, 'text' => $text]);
    }
}
