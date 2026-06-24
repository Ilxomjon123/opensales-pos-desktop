<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Window;
use Throwable;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Native ilova ishga tushganda bir marta chaqiriladi.
     */
    public function boot(): void
    {
        $this->prepareDatabase();

        Window::open()
            ->title('OpenSales POS')
            ->width(1280)
            ->height(820)
            ->minWidth(1024)
            ->minHeight(680)
            ->maximized()
            ->url('/');
    }

    /**
     * Offline DB tayyorlash — birinchi ishga tushishda SQLite migrate qilinadi,
     * bo'sh bo'lsa boshlang'ich ma'lumot (diller, kassir, mahsulotlar) seed bo'ladi.
     */
    private function prepareDatabase(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);

            if (! Schema::hasTable('users') || User::query()->doesntExist()) {
                Artisan::call('db:seed', ['--force' => true]);
            }
        } catch (Throwable) {
            // Migratsiya/seed xatosi ilovani to'xtatmasin — log Laravel tomonidan yoziladi.
        }
    }

    /**
     * Qo'shilgan PHP runtime uchun php.ini direktivalari.
     *
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'upload_max_filesize' => '20M',
            'post_max_size' => '20M',
        ];
    }
}
