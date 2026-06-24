<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupArchive;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToListContents;
use Mockery;
use Tests\TestCase;

final class BackupDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Config::set('backup.disk', 'local');
        Storage::disk('local')->put('backups/dealer_bot_2026.sql.gz', 'BACKUP_BYTES');
    }

    public function test_super_admin_can_download_backup(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.backups.download', ['file' => 'dealer_bot_2026.sql.gz']));

        $response->assertOk();
        $this->assertSame('BACKUP_BYTES', $response->streamedContent());
    }

    public function test_non_super_admin_cannot_access(): void
    {
        $dealer = User::factory()->create();

        // EnsureSuperAdmin super admin bo'lmaganni redirect qiladi
        $this->actingAs($dealer)
            ->get(route('admin.backups.download', ['file' => 'dealer_bot_2026.sql.gz']))
            ->assertRedirect();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.backups.download', ['file' => 'dealer_bot_2026.sql.gz']))
            ->assertRedirect();
    }

    public function test_invalid_or_missing_file_returns_404(): void
    {
        $admin = User::factory()->superAdmin()->create();

        // Path traversal / noto'g'ri kengaytma
        $this->actingAs($admin)
            ->get(route('admin.backups.download', ['file' => 'evil.txt']))
            ->assertNotFound();

        // Mavjud bo'lmagan
        $this->actingAs($admin)
            ->get(route('admin.backups.download', ['file' => 'nope_2025.sql.gz']))
            ->assertNotFound();
    }

    public function test_archive_lists_only_valid_backups_newest_first(): void
    {
        Storage::disk('local')->put('backups/dealer_bot_2025.sql.gz', 'OLD');
        touch(Storage::disk('local')->path('backups/dealer_bot_2025.sql.gz'), now()->subDays(5)->getTimestamp());
        Storage::disk('local')->put('backups/notes.txt', 'IGNORED');

        $all = app(BackupArchive::class)->all();

        $this->assertCount(2, $all);
        $this->assertSame('dealer_bot_2026.sql.gz', $all->first()->name);
        $this->assertSame('dealer_bot_2025.sql.gz', $all->last()->name);
    }

    public function test_archive_returns_empty_when_directory_unreadable(): void
    {
        // Prod'da web user (www-data) backups/ papkasini o'qiy olmasligi mumkin —
        // Flysystem UnableToListContents tashlaydi. Panel buzilmasligi kerak.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('files')
            ->with('backups')
            ->andThrow(UnableToListContents::atLocation('backups', false, new \RuntimeException('Permission denied')));

        Storage::shouldReceive('disk')->andReturn($disk);

        $this->assertTrue(app(BackupArchive::class)->all()->isEmpty());
    }
}
