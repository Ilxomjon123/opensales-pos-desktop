<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Contracts\DatabaseDumperInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class BackupDatabaseCommandTest extends TestCase
{
    public function test_skips_on_non_pgsql_connection(): void
    {
        // Test muhiti sqlite — buyruq xavfsiz o'tkazib yuborilishi kerak
        $this->artisan('db:backup')
            ->expectsOutputToContain('faqat PostgreSQL uchun')
            ->assertSuccessful();
    }

    public function test_creates_backup_file(): void
    {
        Storage::fake('local');
        $this->fakePgsqlConnection();
        $this->fakeDumper();

        $this->artisan('db:backup')
            ->expectsOutputToContain('Backup tayyor')
            ->assertSuccessful();

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('backups/testdb_', $files[0]);
    }

    public function test_prunes_backups_older_than_keep_days(): void
    {
        Storage::fake('local');
        $this->fakePgsqlConnection();
        $this->fakeDumper();

        // 30 kun oldingi eski backup
        Storage::disk('local')->put('backups/testdb_old.sql.gz', 'OLD');
        touch(Storage::disk('local')->path('backups/testdb_old.sql.gz'), now()->subDays(30)->getTimestamp());

        // Boshqa baza fayli — tegmasligi kerak
        Storage::disk('local')->put('backups/otherdb_old.sql.gz', 'KEEP');
        touch(Storage::disk('local')->path('backups/otherdb_old.sql.gz'), now()->subDays(30)->getTimestamp());

        $this->artisan('db:backup', ['--keep' => 14])->assertSuccessful();

        Storage::disk('local')->assertMissing('backups/testdb_old.sql.gz');
        Storage::disk('local')->assertExists('backups/otherdb_old.sql.gz');
    }

    private function fakePgsqlConnection(): void
    {
        Config::set('database.default', 'pgtest');
        Config::set('database.connections.pgtest', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => '5432',
            'username' => 'postgres',
            'password' => 'secret',
            'database' => 'testdb',
        ]);
    }

    /**
     * pg_dump'ni chaqirmaslik uchun PostgresDumper'ni fake bilan almashtiramiz.
     */
    private function fakeDumper(): void
    {
        $dumper = Mockery::mock(DatabaseDumperInterface::class);
        $dumper->shouldReceive('dump')
            ->andReturnUsing(function (array $conn, string $absolutePath): void {
                file_put_contents($absolutePath, 'FAKE_GZIP_CONTENT');
            });

        $this->app->instance(DatabaseDumperInterface::class, $dumper);
    }
}
