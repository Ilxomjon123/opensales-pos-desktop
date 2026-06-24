<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Offline POS desktop — biznes (diller/kassir/mijoz) + namuna katalog.
        $this->call(OfflineSeeder::class);
        $this->call(ProductCatalogSeeder::class);
    }
}
