<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Telefon raqami uchun bir martalik kod (OTP). Redis/cache da saqlanadi.
 * Mobil ilovaga kirishning asosiy yo'li — telefonni tasdiqlash.
 * Kod 5 daqiqa amal qiladi, daqiqada bir marta qayta yuborish mumkin.
 */
final class OtpService
{
    private const TTL_SECONDS = 300;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly SmsService $sms,
    ) {}

    /**
     * Kod yuboradi. Cooldown ichida bo'lsa false qaytaradi.
     */
    public function request(string $phone): bool
    {
        $phone = $this->normalize($phone);

        // Reviewer test raqami: SMS yubormaymiz, kod doim REVIEW_OTP_CODE.
        if ($this->isReviewPhone($phone)) {
            return true;
        }

        if ($this->cache->has($this->cooldownKey($phone))) {
            return false;
        }

        $code = $this->generateCode();

        $this->cache->put($this->codeKey($phone), [
            'code' => $code,
            'attempts' => 0,
        ], self::TTL_SECONDS);

        $this->cache->put($this->cooldownKey($phone), true, self::RESEND_COOLDOWN_SECONDS);

        $this->sms->send($phone, "Tasdiqlash kodi: {$code}");

        return true;
    }

    /**
     * Kodni tekshiradi. To'g'ri bo'lsa kodni o'chiradi va true qaytaradi.
     */
    public function verify(string $phone, string $code): bool
    {
        $phone = $this->normalize($phone);

        // Reviewer test raqami: prod'da ham REVIEW_OTP_CODE qabul qilinadi.
        if ($this->isReviewPhone($phone)) {
            return hash_equals((string) config('services.sms.review_code'), trim($code));
        }

        // Dev/test: fake rejimda 000000 doimo qabul qilinadi (cooldown/iste'molsiz).
        if (! app()->isProduction()
            && (bool) config('services.sms.fake_code', true)
            && trim($code) === '000000') {
            $this->cache->forget($this->codeKey($phone));

            return true;
        }

        $key = $this->codeKey($phone);

        /** @var array{code: string, attempts: int}|null $data */
        $data = $this->cache->get($key);

        if ($data === null) {
            return false;
        }

        if ($data['attempts'] >= self::MAX_ATTEMPTS) {
            $this->cache->forget($key);

            return false;
        }

        if (! hash_equals($data['code'], trim($code))) {
            $data['attempts']++;
            $this->cache->put($key, $data, self::TTL_SECONDS);

            return false;
        }

        $this->cache->forget($key);

        return true;
    }

    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return '+'.$digits;
    }

    /**
     * Allaqachon normalizatsiya qilingan raqam App Store/Google Play reviewer
     * test raqamiga mosmi? Faqat REVIEW_OTP_PHONE va REVIEW_OTP_CODE ikkalasi
     * ham .env da to'ldirilganda faol — aks holda har doim false (xususiyat o'chiq).
     */
    private function isReviewPhone(string $normalizedPhone): bool
    {
        $reviewPhone = config('services.sms.review_phone');
        $reviewCode = config('services.sms.review_code');

        if (! is_string($reviewPhone) || $reviewPhone === ''
            || ! is_string($reviewCode) || $reviewCode === '') {
            return false;
        }

        return hash_equals($this->normalize($reviewPhone), $normalizedPhone);
    }

    private function generateCode(): string
    {
        // Local/test muhitda barqaror kod — qo'lda tekshirishni osonlashtiradi.
        if (! app()->isProduction() && (bool) config('services.sms.fake_code', true)) {
            return '000000';
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function codeKey(string $phone): string
    {
        return "mobile_otp:code:{$phone}";
    }

    private function cooldownKey(string $phone): string
    {
        return "mobile_otp:cooldown:{$phone}";
    }
}
