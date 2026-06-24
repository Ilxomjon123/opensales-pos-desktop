<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Geo ma'lumotini to'liq seed qiladi: davlatlar + har bir davlat ierarxiyasi.
 * Mustaqil ishlatish: `php artisan db:seed --class=GeoSeeder`.
 */
final class GeoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            UzGeoSeeder::class,
            RuGeoSeeder::class,
        ]);
    }
}
