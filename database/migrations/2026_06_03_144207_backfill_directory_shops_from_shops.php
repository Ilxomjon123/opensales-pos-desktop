<?php

declare(strict_types=1);

use App\Models\Shop;
use App\Services\DirectoryShopService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = new DirectoryShopService;

        // Eng eski shopdan boshlab — birinchi yozuv spravochnik egasi bo'ladi,
        // keyingilari (boshqa dealerdagi dublikatlar) shu yozuvga bog'lanadi.
        Shop::query()
            ->orderBy('id')
            ->chunkById(500, function ($shops) use ($service): void {
                foreach ($shops as $shop) {
                    $service->syncFromShop($shop, source: 'backfill');
                }
            });
    }

    public function down(): void
    {
        // directory_id null qilinadi; directory_shops jadvali create-migration down()'ida tushadi.
        Shop::query()->whereNotNull('directory_id')->update(['directory_id' => null]);
    }
};
