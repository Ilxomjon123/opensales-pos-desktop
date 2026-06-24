<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Google va Yandex xarita linklaridan koordinatalarni ajratib oluvchi helper.
 *
 * Yandex `ll`/`pt` parametrida tartibi: longitude,latitude.
 * Google `@`/`q`/`ll` parametrlarida tartibi: latitude,longitude.
 */
final class MapLinkParser
{
    /**
     * Linkdan yoki matndan birinchi topilgan (lat, lng) ni qaytaradi.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function parse(string $input): ?array
    {
        $text = trim($input);

        if ($text === '') {
            return null;
        }

        // Plain "41.31, 69.27" yoki "41.31 69.27"
        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*[,\s]\s*(-?\d+(?:\.\d+)?)$/', $text, $m)) {
            return self::valid((float) $m[1], (float) $m[2]);
        }

        $decoded = rawurldecode($text);
        $isYandex = (bool) preg_match('#yandex\.[a-z.]+/maps#i', $decoded);

        // Google patterns. `,\+?` — `+` belgisi probelni URL-encoded shakli sifatida ishlatiladi
        // (masalan `/search/41.001,+71.672`).
        $googlePatterns = [
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
            '#/maps/search/(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)#',
            '#/maps/dir/(?:[^/]*/)*?(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)#',
            '#/maps/place/(?:[^/]+/)?@(-?\d+\.\d+),(-?\d+\.\d+)#',
            '/@(-?\d+\.\d+),(-?\d+\.\d+)/',
            '/q=loc:(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)/',
            '/[?&]q=(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)/',
        ];

        if (! $isYandex) {
            foreach ($googlePatterns as $pattern) {
                if (preg_match($pattern, $decoded, $m)) {
                    $coords = self::valid((float) $m[1], (float) $m[2]);

                    if ($coords !== null) {
                        return $coords;
                    }
                }
            }
        } elseif (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $decoded, $m)) {
            // !3d!4d Yandex linkida ham (umumiy holatda Google embed) topilishi mumkin
            $coords = self::valid((float) $m[1], (float) $m[2]);

            if ($coords !== null) {
                return $coords;
            }
        }

        // ll=a,b — Yandex: lng,lat | Google: lat,lng
        if (preg_match('/[?&]ll=(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)/', $decoded, $m)) {
            $a = (float) $m[1];
            $b = (float) $m[2];
            $lat = $isYandex ? $b : $a;
            $lng = $isYandex ? $a : $b;
            $coords = self::valid($lat, $lng);

            if ($coords !== null) {
                return $coords;
            }
        }

        // pt=lng,lat (Yandex point)
        if (preg_match('/[?&]pt=(-?\d+\.\d+),(-?\d+\.\d+)/', $decoded, $m)) {
            $coords = self::valid((float) $m[2], (float) $m[1]);

            if ($coords !== null) {
                return $coords;
            }
        }

        return null;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private static function valid(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        // Toshkent atrofida (0,0) noto'g'ri natija — bo'sh deb hisoblanadi
        if ($lat === 0.0 && $lng === 0.0) {
            return null;
        }

        return [
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
        ];
    }
}
