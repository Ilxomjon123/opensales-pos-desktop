<?php

declare(strict_types=1);

namespace App\Support;

/**
 * O'zbekiston 14 ta viloyat va ular ichidagi tumanlar (2024).
 * Form uchun select variantlari va STIR bo'yicha topilgan lokatsiyani
 * normallashtirish uchun ishlatiladi.
 */
final class UzRegions
{
    /**
     * @return array<int, array{name: string, districts: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'Toshkent shahri',
                'districts' => [
                    'Bektemir tumani', 'Chilonzor tumani', 'Mirobod tumani', 'Mirzo Ulug\'bek tumani',
                    'Olmazor tumani', 'Sergeli tumani', 'Shayxontohur tumani', 'Uchtepa tumani',
                    'Yakkasaroy tumani', 'Yangi Hayot tumani', 'Yashnobod tumani', 'Yunusobod tumani',
                ],
            ],
            [
                'name' => 'Toshkent viloyati',
                'districts' => [
                    'Bekobod tumani', 'Bekobod shahri', 'Bo\'ka tumani', 'Bo\'stonliq tumani',
                    'Chinoz tumani', 'Ohangaron tumani', 'Ohangaron shahri', 'Oqqo\'rg\'on tumani',
                    'Parkent tumani', 'Piskent tumani', 'Quyi Chirchiq tumani', 'O\'rta Chirchiq tumani',
                    'Yangiyo\'l tumani', 'Yangiyo\'l shahri', 'Yuqori Chirchiq tumani', 'Zangiota tumani',
                    'Chirchiq shahri', 'Olmaliq shahri', 'Angren shahri', 'Nurafshon shahri',
                ],
            ],
            [
                'name' => 'Andijon viloyati',
                'districts' => [
                    'Andijon tumani', 'Andijon shahri', 'Asaka tumani', 'Baliqchi tumani',
                    'Bo\'z tumani', 'Buloqboshi tumani', 'Izboskan tumani', 'Jalaquduq tumani',
                    'Marhamat tumani', 'Oltinko\'l tumani', 'Paxtaobod tumani', 'Qo\'rg\'ontepa tumani',
                    'Shahrixon tumani', 'Ulug\'nor tumani', 'Xo\'jaobod tumani',
                ],
            ],
            [
                'name' => 'Namangan viloyati',
                'districts' => [
                    'Chortoq tumani', 'Chust tumani', 'Davlatobod tumani', 'Kosonsoy tumani',
                    'Mingbuloq tumani', 'Namangan tumani', 'Namangan shahri', 'Norin tumani',
                    'Pop tumani', 'To\'raqo\'rg\'on tumani', 'Uychi tumani', 'Uchqo\'rg\'on tumani',
                    'Yangiqo\'rg\'on tumani',
                ],
            ],
            [
                'name' => 'Farg\'ona viloyati',
                'districts' => [
                    'Bag\'dod tumani', 'Beshariq tumani', 'Buvayda tumani', 'Dang\'ara tumani',
                    'Farg\'ona tumani', 'Farg\'ona shahri', 'Furqat tumani', 'Oltiariq tumani',
                    'O\'zbekiston tumani', 'Qo\'shtepa tumani', 'Quva tumani', 'Rishton tumani',
                    'So\'x tumani', 'Toshloq tumani', 'Uchko\'prik tumani', 'Yozyovon tumani',
                    'Qo\'qon shahri', 'Marg\'ilon shahri', 'Quvasoy shahri',
                ],
            ],
            [
                'name' => 'Samarqand viloyati',
                'districts' => [
                    'Bulung\'ur tumani', 'Ishtixon tumani', 'Jomboy tumani', 'Kattaqo\'rg\'on tumani',
                    'Kattaqo\'rg\'on shahri', 'Narpay tumani', 'Nurobod tumani', 'Oqdaryo tumani',
                    'Past-Darg\'om tumani', 'Paxtachi tumani', 'Payariq tumani', 'Qo\'shrabot tumani',
                    'Samarqand tumani', 'Samarqand shahri', 'Tayloq tumani', 'Urgut tumani',
                ],
            ],
            [
                'name' => 'Buxoro viloyati',
                'districts' => [
                    'Buxoro tumani', 'Buxoro shahri', 'G\'ijduvon tumani', 'Jondor tumani',
                    'Kogon tumani', 'Kogon shahri', 'Olot tumani', 'Peshku tumani',
                    'Qorako\'l tumani', 'Qorovulbozor tumani', 'Romitan tumani', 'Shofirkon tumani',
                    'Vobkent tumani',
                ],
            ],
            [
                'name' => 'Navoiy viloyati',
                'districts' => [
                    'Karmana tumani', 'Konimex tumani', 'Navbahor tumani', 'Navoiy shahri',
                    'Nurota tumani', 'Qiziltepa tumani', 'Tomdi tumani', 'Uchquduq tumani',
                    'Xatirchi tumani', 'Zarafshon shahri',
                ],
            ],
            [
                'name' => 'Qashqadaryo viloyati',
                'districts' => [
                    'Chiroqchi tumani', 'Dehqonobod tumani', 'G\'uzor tumani', 'Kasbi tumani',
                    'Kitob tumani', 'Koson tumani', 'Mirishkor tumani', 'Muborak tumani',
                    'Nishon tumani', 'Qamashi tumani', 'Qarshi tumani', 'Qarshi shahri',
                    'Shahrisabz tumani', 'Shahrisabz shahri', 'Yakkabog\' tumani',
                ],
            ],
            [
                'name' => 'Surxondaryo viloyati',
                'districts' => [
                    'Angor tumani', 'Bandixon tumani', 'Boysun tumani', 'Denov tumani',
                    'Jarqo\'rg\'on tumani', 'Muzrabot tumani', 'Oltinsoy tumani', 'Qiziriq tumani',
                    'Qumqo\'rg\'on tumani', 'Sariosiyo tumani', 'Sherobod tumani', 'Sho\'rchi tumani',
                    'Termiz tumani', 'Termiz shahri', 'Uzun tumani',
                ],
            ],
            [
                'name' => 'Sirdaryo viloyati',
                'districts' => [
                    'Boyovut tumani', 'Guliston tumani', 'Guliston shahri', 'Mirzaobod tumani',
                    'Oqoltin tumani', 'Sardoba tumani', 'Sayxunobod tumani', 'Sirdaryo tumani',
                    'Xovos tumani', 'Shirin shahri', 'Yangiyer shahri',
                ],
            ],
            [
                'name' => 'Jizzax viloyati',
                'districts' => [
                    'Arnasoy tumani', 'Baxmal tumani', 'Do\'stlik tumani', 'Forish tumani',
                    'G\'allaorol tumani', 'Mirzacho\'l tumani', 'Paxtakor tumani', 'Sharof Rashidov tumani',
                    'Yangiobod tumani', 'Zafarobod tumani', 'Zarbdor tumani', 'Zomin tumani',
                    'Jizzax shahri',
                ],
            ],
            [
                'name' => 'Xorazm viloyati',
                'districts' => [
                    'Bog\'ot tumani', 'Gurlan tumani', 'Qo\'shko\'pir tumani', 'Shovot tumani',
                    'Urganch tumani', 'Urganch shahri', 'Xazorasp tumani', 'Xiva tumani',
                    'Xiva shahri', 'Xonqa tumani', 'Yangiariq tumani', 'Yangibozor tumani',
                    'Tuproqqal\'a tumani',
                ],
            ],
            [
                'name' => 'Qoraqalpog\'iston Respublikasi',
                'districts' => [
                    'Amudaryo tumani', 'Beruniy tumani', 'Bo\'zatov tumani', 'Chimboy tumani',
                    'Ellikqal\'a tumani', 'Kegeyli tumani', 'Mo\'ynoq tumani', 'Nukus tumani',
                    'Nukus shahri', 'Qo\'ng\'irot tumani', 'Qonliko\'l tumani', 'Qorao\'zak tumani',
                    'Shumanay tumani', 'Taxiatosh tumani', 'Taxtako\'pir tumani', 'To\'rtko\'l tumani',
                    'Xo\'jayli tumani',
                ],
            ],
        ];
    }

    /**
     * "Toshkent shahri, Bektemir tumani" ko'rinishidagi matnni
     * eng yaqin viloyat va tuman bilan moslaydi. Cyrillic Russian va Uzbek
     * variantlari ham qo'llab-quvvatlanadi.
     *
     * @return array{region: string|null, district: string|null}
     */
    public static function match(?string $region, ?string $district): array
    {
        if ($region === null && $district === null) {
            return ['region' => null, 'district' => null];
        }

        $matchedRegion = $region !== null ? self::matchRegion($region) : null;
        $matchedDistrict = null;

        if ($district !== null) {
            $districtMatch = self::matchDistrict($district, $matchedRegion);

            if ($districtMatch !== null) {
                // Tuman alias regionni inference qilishi mumkin
                $matchedRegion ??= $districtMatch['region'];
                $matchedDistrict = $districtMatch['district'];
            } elseif ($matchedRegion === null) {
                // Tuman emas, balki shahar/viloyat indikatori bo'lishi mumkin (masalan "Toshkent")
                $matchedRegion = self::matchRegion($district);
            }
        }

        return ['region' => $matchedRegion, 'district' => $matchedDistrict];
    }

    private static function matchRegion(string $region): ?string
    {
        $key = self::normalize($region);

        $aliases = self::regionAliases();

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        foreach (self::all() as $r) {
            if (self::normalize($r['name']) === $key) {
                return $r['name'];
            }
        }

        foreach (self::all() as $r) {
            $rKey = self::normalize($r['name']);

            if (str_contains($rKey, $key) || str_contains($key, $rKey)) {
                return $r['name'];
            }
        }

        return null;
    }

    /**
     * @return array{region: string, district: string}|null
     */
    private static function matchDistrict(string $district, ?string $regionHint): ?array
    {
        $key = self::normalize($district);

        $aliases = self::districtAliases();

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        $regions = self::all();

        if ($regionHint !== null) {
            $target = array_values(array_filter($regions, fn ($r) => $r['name'] === $regionHint))[0] ?? null;

            if ($target !== null) {
                foreach ($target['districts'] as $d) {
                    $dKey = self::normalize($d);

                    if ($dKey === $key || str_contains($dKey, $key) || str_contains($key, $dKey)) {
                        return ['region' => $regionHint, 'district' => $d];
                    }
                }
            }
        }

        // Region hint yo'q yoki match qilmadi — barcha regionlardan qidiramiz
        foreach ($regions as $r) {
            foreach ($r['districts'] as $d) {
                if (self::normalize($d) === $key) {
                    return ['region' => $r['name'], 'district' => $d];
                }
            }
        }

        return null;
    }

    /**
     * Viloyat nomi variantlari (kanonik => xom aliaslar). Seeder shu manbadan
     * `region_aliases` jadvalini to'ldiradi.
     *
     * @return array<string, list<string>>
     */
    public static function regionAliasDefinitions(): array
    {
        return [
            'Toshkent shahri' => [
                'Тошкент шаҳри', 'Тошкент шахри', 'Toshkent', 'Тошкент',
                'Ташкент', 'г. Ташкент', 'г.Ташкент', 'город Ташкент',
                'Tashkent', 'Tashkent City',
            ],
            'Toshkent viloyati' => [
                'Тошкент вилояти', 'Toshkent', 'Ташкентская область',
                'Ташкентская обл.', 'Tashkent Region', 'Tashkent Province',
            ],
            'Andijon viloyati' => [
                'Андижон вилояти', 'Андижанская область', 'Андижан',
                'Andijan Region', 'Andijan Province',
            ],
            'Namangan viloyati' => [
                'Наманган вилояти', 'Наманганская область', 'Наманган',
                'Namangan Region', 'Namangan Province',
            ],
            'Farg\'ona viloyati' => [
                'Фарғона вилояти', 'Фергана', 'Ферганская область',
                'Fergana Region', 'Fergana Province',
            ],
            'Samarqand viloyati' => [
                'Самарқанд вилояти', 'Самарканд', 'Самаркандская область',
                'Samarkand Region', 'Samarkand Province',
            ],
            'Buxoro viloyati' => [
                'Бухоро вилояти', 'Бухара', 'Бухарская область',
                'Bukhara Region', 'Bukhara Province',
            ],
            'Navoiy viloyati' => [
                'Навоий вилояти', 'Навоийская область', 'Навои',
                'Navoiy Region', 'Navoi Region',
            ],
            'Qashqadaryo viloyati' => [
                'Қашқадарё вилояти', 'Кашкадарьинская область',
                'Kashkadarya Region', 'Qashqadaryo Region',
            ],
            'Surxondaryo viloyati' => [
                'Сурхондарё вилояти', 'Сурхандарьинская область',
                'Surkhandarya Region', 'Surxondaryo Region',
            ],
            'Sirdaryo viloyati' => [
                'Сирдарё вилояти', 'Сырдарьинская область',
                'Syrdarya Region', 'Sirdaryo Region',
            ],
            'Jizzax viloyati' => [
                'Жиззах вилояти', 'Джизакская область', 'Джизак',
                'Jizzakh Region', 'Jizzax Region',
            ],
            'Xorazm viloyati' => [
                'Хоразм вилояти', 'Хорезмская область', 'Хорезм',
                'Khorezm Region', 'Xorazm Region',
            ],
            'Qoraqalpog\'iston Respublikasi' => [
                'Қорақалпоғистон Республикаси', 'Республика Каракалпакстан',
                'Каракалпакстан', 'Karakalpakstan', 'Republic of Karakalpakstan',
            ],
        ];
    }

    /**
     * Cyrillic Russian/Uzbek va boshqa variantlarni canonical Latin nomga maplaydi.
     *
     * @return array<string, string>
     */
    private static function regionAliases(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $map = [];

        foreach (self::regionAliasDefinitions() as $canonical => $aliases) {
            $map[self::normalize($canonical)] = $canonical;

            foreach ($aliases as $alias) {
                $map[self::normalize($alias)] = $canonical;
            }
        }

        return $cache = $map;
    }

    /**
     * Tuman nomi variantlari (kanonik => xom aliaslar). Har element:
     * [viloyat, tuman, [aliaslar]]. Seeder shu manbadan foydalanadi.
     *
     * @return list<array{0: string, 1: string, 2: list<string>}>
     */
    public static function districtAliasDefinitions(): array
    {
        return [
            // Toshkent shahri 12 ta tumani — Russian + Cyrillic Uzbek variantlari
            ['Toshkent shahri', 'Bektemir tumani', ['Бектемирский район', 'Бектемир тумани']],
            ['Toshkent shahri', 'Chilonzor tumani', ['Чиланзарский район', 'Чилонзор тумани']],
            ['Toshkent shahri', 'Mirobod tumani', ['Мирабадский район', 'Миробод тумани']],
            ['Toshkent shahri', 'Mirzo Ulug\'bek tumani', ['Мирзо-Улугбекский район', 'Мирзо Улугбекский район', 'Мирзо Улуғбек тумани']],
            ['Toshkent shahri', 'Olmazor tumani', ['Алмазарский район', 'Олмазор тумани']],
            ['Toshkent shahri', 'Sergeli tumani', ['Сергелийский район', 'Сергели тумани']],
            ['Toshkent shahri', 'Shayxontohur tumani', ['Шайхантахурский район', 'Шайхонтохур тумани', 'Шайхонтоҳур тумани']],
            ['Toshkent shahri', 'Uchtepa tumani', ['Учтепинский район', 'Учтепа тумани']],
            ['Toshkent shahri', 'Yakkasaroy tumani', ['Яккасарайский район', 'Яккасарой тумани']],
            ['Toshkent shahri', 'Yangi Hayot tumani', ['Янгихаётский район', 'Янги Ҳаёт тумани', 'Янги Хаёт тумани']],
            ['Toshkent shahri', 'Yashnobod tumani', ['Яшнабадский район', 'Яшнобод тумани']],
            ['Toshkent shahri', 'Yunusobod tumani', ['Юнусабадский район', 'Юнусобод тумани']],
        ];
    }

    /**
     * Cyrillic district nomlari uchun explicit mapping (ayniqsa Toshkent shahri tumanlari).
     *
     * @return array<string, array{region: string, district: string}>
     */
    private static function districtAliases(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $map = [];

        foreach (self::districtAliasDefinitions() as [$region, $district, $aliases]) {
            $map[self::normalize($district)] = ['region' => $region, 'district' => $district];

            foreach ($aliases as $alias) {
                $map[self::normalize($alias)] = ['region' => $region, 'district' => $district];
            }
        }

        return $cache = $map;
    }

    private static function normalize(string $s): string
    {
        return GeoText::normalize($s);
    }
}
