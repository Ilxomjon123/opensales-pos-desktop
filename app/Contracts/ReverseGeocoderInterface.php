<?php

declare(strict_types=1);

namespace App\Contracts;

interface ReverseGeocoderInterface
{
    /**
     * Koordinatadan UZ viloyat/tuman/manzilni qaytaradi. Servis javob bermasa
     * yoki ma'lumot topilmasa — barcha maydonlar null bo'lgan array qaytadi.
     * `country_code` ISO 3166-1 alpha-2 kichik harfda ("uz", "kz", ...).
     *
     * `$lang` — Nominatim Accept-Language (masalan "ru" yoki "uz,ru,en").
     *
     * @return array{region: string|null, district: string|null, address: string|null, country_code: string|null}
     */
    public function reverse(float $lat, float $lng, ?string $lang = null): array;
}
