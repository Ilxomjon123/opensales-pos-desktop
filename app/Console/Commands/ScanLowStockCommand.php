<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\StockAlertService;
use Illuminate\Console\Command;

/**
 * Har kuni low stock ga tushgan mahsulotlarni skanerlab,
 * ularning har biri uchun (cooldown ga rioya qilgan holda) event dispatch qiladi.
 * Sababi: OrderService orqali dispatch oqim-bo'yicha ishlaydi, lekin
 * narx/min_stock qo'lda o'zgartirilsa yoki yangi threshold belgilasa, hech qaysi event
 * chaqirilmaydi. Kunlik skan shuni qamrab oladi.
 */
final class ScanLowStockCommand extends Command
{
    protected $signature = 'stock:scan-low';

    protected $description = 'Low stock mahsulotlarni skanlaydi va dillerlarga xabar yuboradi';

    public function handle(StockAlertService $service): int
    {
        $count = 0;

        Product::query()
            ->lowStock()
            ->where('is_active', true)
            ->chunkById(200, function ($products) use ($service, &$count): void {
                foreach ($products as $product) {
                    if ($service->checkAndNotify($product) === true) {
                        $count++;
                    }
                }
            });

        $this->info("Low stock skaner: {$count} ta bildirishnoma yuborildi");

        return self::SUCCESS;
    }
}
