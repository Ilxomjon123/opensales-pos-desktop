<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Telegram orqali kirish (SMS'siz). Oqim:
 *  1. App start() — login token oladi, t.me/<bot>?start=auth_<token> ochadi.
 *  2. Bot /start auth_<token> — telegram_id uchun pending token saqlaydi,
 *     "Raqamni ulashish" tugmasini ko'rsatadi.
 *  3. Contact kelganda confirm() — telefon+telegram_id bo'yicha Customer
 *     yaratadi/topadi, telegramdagi diller/shoplarni unga bog'laydi.
 *  4. App poll() — confirmed bo'lsa Sanctum token oladi.
 */
final class TelegramLoginService
{
    private const TTL = 600;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly MobileAppLinkService $link,
        private readonly OtpService $otp,
    ) {}

    /** App: yangi login token (app tili bilan — bot xabari shu tilda bo'ladi). */
    public function start(?string $locale = null): string
    {
        $token = bin2hex(random_bytes(16));
        $this->cache->put($this->key($token), ['status' => 'pending', 'locale' => $locale], self::TTL);

        return $token;
    }

    /**
     * App (allaqachon kirgan): telegram'ni ULASH tokeni. Login'dan farqi —
     * token joriy customer'ga bog'langan, bot telefon SO'RAMAYDI, to'g'ridan-to'g'ri
     * shu customer'ga telegram a'zoliklarini bog'laydi.
     */
    public function startLink(int $customerId, ?string $locale = null): string
    {
        $token = bin2hex(random_bytes(16));
        $this->cache->put($this->key($token), [
            'status' => 'pending',
            'kind' => 'link',
            'customer_id' => $customerId,
            'locale' => $locale,
        ], self::TTL);

        return $token;
    }

    /** Token bilan saqlangan app tili (bot xabarlari uchun). */
    public function tokenLocale(string $token): ?string
    {
        $data = $this->cache->get($this->key($token));

        return is_array($data) ? ($data['locale'] ?? null) : null;
    }

    /**
     * Bot: /start link_<token> — telefon so'ramay, tokenga bog'langan customer'ga
     * shu telegram_id ning barcha diller/shop a'zoliklarini bog'laydi.
     */
    public function confirmLink(string $token, int $telegramId): bool
    {
        $data = $this->cache->get($this->key($token));

        if (! is_array($data) || ($data['kind'] ?? null) !== 'link') {
            return false;
        }

        $customer = Customer::query()->find($data['customer_id'] ?? null);
        if ($customer === null) {
            return false;
        }

        $this->link->link($customer, $telegramId);

        $this->cache->put($this->key($token), [
            'status' => 'confirmed',
            'kind' => 'link',
            'customer_id' => $customer->id,
        ], self::TTL);

        return true;
    }

    /** Bot: foydalanuvchi /start auth_<token> bosdi — kutilayotgan tokenni eslab qolamiz. */
    public function setPending(int $telegramId, string $token): bool
    {
        if ($this->cache->get($this->key($token)) === null) {
            return false; // noma'lum/eskirgan token
        }
        $this->cache->put($this->pendingKey($telegramId), $token, self::TTL);

        return true;
    }

    public function pendingToken(int $telegramId): ?string
    {
        $t = $this->cache->get($this->pendingKey($telegramId));

        return is_string($t) ? $t : null;
    }

    /** Bot: contact kelganda — Customer yaratish/topish + telegram a'zoliklarini bog'lash. */
    public function confirm(string $token, int $telegramId, string $phone, ?string $name): bool
    {
        if ($this->cache->get($this->key($token)) === null) {
            return false;
        }

        $normalized = $this->otp->normalize($phone);

        /** @var Customer $customer */
        $customer = Customer::query()->firstOrCreate(
            ['phone' => $normalized],
            ['is_active' => true],
        );

        if ($name !== null && $name !== '' && ($customer->name === null || $customer->name === '')) {
            $customer->name = $name;
        }
        $customer->forceFill(['last_login_at' => now()])->save();

        // Telegramdagi barcha diller/shop a'zoliklari shu customer'ga (mobil'da chiqsin).
        $this->link->link($customer, $telegramId);

        $this->cache->put(
            $this->key($token),
            ['status' => 'confirmed', 'customer_id' => $customer->id],
            self::TTL,
        );
        $this->cache->forget($this->pendingKey($telegramId));

        return true;
    }

    /**
     * App: token holatini tekshiradi. Confirmed bo'lsa Sanctum token qaytaradi.
     *
     * @return array{status: string, token?: string, has_membership?: bool, name?: ?string}
     */
    public function poll(string $token): array
    {
        $data = $this->cache->get($this->key($token));

        if ($data === null) {
            return ['status' => 'expired'];
        }
        if (($data['status'] ?? null) !== 'confirmed') {
            return ['status' => 'pending'];
        }

        $customer = Customer::query()->find($data['customer_id']);
        if ($customer === null) {
            return ['status' => 'expired'];
        }

        $this->cache->forget($this->key($token));

        return [
            'status' => 'confirmed',
            'token' => $customer->createToken('mobile')->plainTextToken,
            'has_membership' => $customer->shopMembers()->active()->exists(),
            'name' => $customer->name,
        ];
    }

    private function key(string $token): string
    {
        return "tg_login:{$token}";
    }

    private function pendingKey(int $telegramId): string
    {
        return "tg_login_pending:{$telegramId}";
    }
}
