<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Eskiz.uz SMS shlyuzi. Token cache da (~25 kun), 401 da avtomatik qayta login.
 * Sozlamalar: config('services.eskiz').
 */
final class EskizClient
{
    private const TOKEN_TTL = 60 * 60 * 24 * 25; // ~25 kun

    public function __construct(private readonly CacheRepository $cache) {}

    public function send(string $phone, string $text): bool
    {
        $to = $this->normalize($phone);

        $res = $this->post($to, $text, $this->token());

        // Token muddati tugagan bo'lsa — yangilab qayta urinamiz.
        if ($res === 401) {
            $res = $this->post($to, $text, $this->token(fresh: true));
        }

        if ($res !== 200) {
            Log::warning('Eskiz SMS yuborilmadi', ['phone' => $to, 'status' => $res]);

            return false;
        }

        return true;
    }

    private function post(string $phone, string $text, string $token): int
    {
        $response = Http::withToken($token)
            ->asMultipart()
            ->post($this->base().'/api/message/sms/send', [
                ['name' => 'mobile_phone', 'contents' => $phone],
                ['name' => 'message', 'contents' => $text],
                ['name' => 'from', 'contents' => (string) config('services.eskiz.from', '4546')],
            ]);

        return $response->status();
    }

    private function token(bool $fresh = false): string
    {
        $key = 'eskiz_token';

        if (! $fresh) {
            $cached = $this->cache->get($key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $email = (string) config('services.eskiz.email');
        $password = (string) config('services.eskiz.password');

        if ($email === '' || $password === '') {
            throw new RuntimeException('Eskiz email/password sozlanmagan.');
        }

        $token = (string) data_get(
            Http::asMultipart()->post($this->base().'/api/auth/login', [
                ['name' => 'email', 'contents' => $email],
                ['name' => 'password', 'contents' => $password],
            ])->json(),
            'data.token',
            '',
        );

        if ($token === '') {
            throw new RuntimeException('Eskiz token olinmadi.');
        }

        $this->cache->put($key, $token, self::TOKEN_TTL);

        return $token;
    }

    private function base(): string
    {
        return rtrim((string) config('services.eskiz.base_url', 'https://notify.eskiz.uz'), '/');
    }

    private function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
