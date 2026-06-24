<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Offline POS desktop — faqat lokal biznes ma'lumoti (geo kerak emas).
        $this->call(OfflineSeeder::class);
    }
}
