<?php

declare(strict_types=1);

namespace App\Support;

final class GeoText
{
    /**
     * Hudud nomini taqqoslash uchun normallashtiradi: apostrof variantlari,
     * probel va punktuatsiya olib tashlanadi, kichik harfga o'tkaziladi.
     *
     * Nominatim `ʻ` (U+02BB, Lm toifa) ishlatadi, kanonik nomlar ASCII `'`.
     * U+02BB `\p{P}` ga kirmaydi, shu sabab apostrof guruhi alohida tozalanadi
     * (aks holda "Toʻraqoʻrgʻon" ≠ "To'raqo'rg'on").
     */
    public static function normalize(string $s): string
    {
        $s = preg_replace("/[\x{0027}\x{0060}\x{00B4}\x{02BB}\x{02BC}\x{2018}\x{2019}]/u", '', $s) ?? $s;

        return mb_strtolower(preg_replace("/[\p{P}\s]+/u", '', $s) ?? $s);
    }
}
