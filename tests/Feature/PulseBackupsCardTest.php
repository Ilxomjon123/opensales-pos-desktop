<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Pulse\Backups;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class PulseBackupsCardTest extends TestCase
{
    public function test_card_boots(): void
    {
        Storage::fake('local');
        Config::set('backup.disk', 'local');
        Storage::disk('local')->put('backups/dealer_bot_2026-06-08_020000.sql.gz', 'X');

        // #[Lazy] — boshlang'ich render placeholder; komponent xatosiz yuklanishini tekshiramiz
        Livewire::test(Backups::class)->assertOk();
    }

    public function test_renders_backups_after_lazy_load(): void
    {
        Storage::fake('local');
        Config::set('backup.disk', 'local');
        Storage::disk('local')->put('backups/dealer_bot_2026-06-08_020000.sql.gz', 'X');

        Livewire::withoutLazyLoading()
            ->test(Backups::class)
            ->assertSee('dealer_bot_2026-06-08_020000.sql.gz')
            ->assertSee('Yuklab olish');
    }
}
