<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Currency;

final class Money
{
    /**
     * Pul summasini guruhlangan ko'rinishda formatlaydi: "1 200 000".
     * So'm va rubl butun sonlarda yuritiladi (kasrsiz).
     */
    public static function format(int|float $amount, Currency $currency): string
    {
        return number_format(round((float) $amount), $currency->fractionDigits(), '.', ' ');
    }

    /**
     * Valyuta belgisi bilan: "1 200 000 so'm" / "1 200 000 ₽".
     */
    public static function formatWithSymbol(int|float $amount, Currency $currency): string
    {
        return self::format($amount, $currency).' '.$currency->symbol();
    }

    /**
     * Catalog'dagi pack_price ni promo discount bilan moslash.
     *
     * - packSize <= 1: pack_price ma'nosi yo'q, null
     * - basePackPrice null bo'lsa: basePrice * packSize fallback
     * - discount yo'q (effective == base): base qiymat o'zgarishsiz
     * - discount bor: base * (effective / base) — base nisbati saqlanadi
     */
    public static function effectivePackPrice(
        ?float $basePackPrice,
        float $basePrice,
        float $effectivePrice,
        float $packSize,
    ): ?float {
        if ($packSize <= 1) {
            return null;
        }

        $base = $basePackPrice ?? round($basePrice * $packSize, 2);

        if ($basePrice <= 0 || abs($effectivePrice - $basePrice) < 0.000001) {
            return $base;
        }

        return round($base * $effectivePrice / $basePrice, 2);
    }
}
