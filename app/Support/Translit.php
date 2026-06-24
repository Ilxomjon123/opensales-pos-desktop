<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Uzbek lotin <-> kirill transliteratsiya yordamchisi.
 *
 * Qidiruv uchun ishlatiladi: foydalanuvchi lotin yoki kirill, katta yoki
 * kichik harfda yozsa ham bir xil natija topilsin.
 */
final class Translit
{
    /**
     * Kirill -> lotin. Ko'p harfli (digraf) variantlar birinchi.
     *
     * @var array<string, string>
     */
    private const CYRILLIC_TO_LATIN = [
        'ё' => 'yo', 'ю' => 'yu', 'я' => 'ya', 'ц' => 'ts', 'щ' => 'sh',
        'ў' => "o'", 'ғ' => "g'", 'ч' => 'ch', 'ш' => 'sh', 'ъ' => "'",
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y',
        'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'x', 'ҳ' => 'h', 'қ' => 'q', 'э' => 'e',
        'ь' => '', 'ы' => 'i',
    ];

    /**
     * Lotin -> kirill. Digraflar birinchi tekshiriladi.
     *
     * @var array<string, string>
     */
    private const LATIN_TO_CYRILLIC = [
        "o'" => 'ў', "g'" => 'ғ', 'sh' => 'ш', 'ch' => 'ч', 'yo' => 'ё',
        'yu' => 'ю', 'ya' => 'я', 'ts' => 'ц', 'ng' => 'нг',
        'a' => 'а', 'b' => 'б', 'd' => 'д', 'e' => 'е', 'f' => 'ф',
        'g' => 'г', 'h' => 'ҳ', 'i' => 'и', 'j' => 'ж', 'k' => 'к',
        'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п',
        'q' => 'қ', 'r' => 'р', 's' => 'с', 't' => 'т', 'u' => 'у',
        'v' => 'в', 'x' => 'х', 'y' => 'й', 'z' => 'з', 'c' => 'к',
        'w' => 'в', "'" => 'ъ',
    ];

    /**
     * Berilgan matnning lotin va kirill variantlarini qaytaradi (kichik harfda).
     *
     * @return list<string>
     */
    public static function variants(string $term): array
    {
        $term = mb_strtolower(trim($term));

        if ($term === '') {
            return [];
        }

        $variants = [$term, self::toLatin($term), self::toCyrillic($term)];

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Berilgan ustunlar bo'yicha registr-sezgir bo'lmagan (ilike),
     * lotin/kirill variantlarini hisobga olgan qidiruvni qo'llaydi.
     *
     * @param  list<string>  $columns
     */
    public static function applyLike(Builder $query, array $columns, string $term): void
    {
        $variants = self::variants($term);

        if ($variants === [] || $columns === []) {
            return;
        }

        // Postgres'da 'ilike' registr-sezgir emas. Boshqa driverlarda (sqlite test
        // muhiti, mysql default collation) 'like' o'zi registr-sezgir emas.
        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query->where(function (Builder $q) use ($columns, $variants, $operator): void {
            foreach ($columns as $column) {
                foreach ($variants as $variant) {
                    $q->orWhere($column, $operator, '%'.$variant.'%');
                }
            }
        });
    }

    public static function toLatin(string $term): string
    {
        return strtr(mb_strtolower($term), self::CYRILLIC_TO_LATIN);
    }

    public static function toCyrillic(string $term): string
    {
        $term = mb_strtolower($term);
        $result = '';
        $length = mb_strlen($term);

        for ($i = 0; $i < $length;) {
            $matched = false;

            // Avval ikki harfli digraflarni tekshir
            if ($i + 1 < $length) {
                $pair = mb_substr($term, $i, 2);
                if (isset(self::LATIN_TO_CYRILLIC[$pair])) {
                    $result .= self::LATIN_TO_CYRILLIC[$pair];
                    $i += 2;
                    $matched = true;
                }
            }

            if (! $matched) {
                $char = mb_substr($term, $i, 1);
                $result .= self::LATIN_TO_CYRILLIC[$char] ?? $char;
                $i++;
            }
        }

        return $result;
    }
}
