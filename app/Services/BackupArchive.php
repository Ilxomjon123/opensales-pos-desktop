<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * db:backup yaratgan backup fayllar ustida ishlash (ro'yxat + yuklab olish).
 * Fayllar config('backup.disk') diskining backups/ papkasida.
 */
final class BackupArchive
{
    private const DIR = 'backups';

    private const NAME_PATTERN = '/^[\w.\-]+\.sql\.gz$/';

    private readonly Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk((string) config('backup.disk', 'local'));
    }

    /**
     * Barcha backup fayllar — eng yangisi birinchi.
     *
     * Papka o'qib bo'lmasa (masalan, web user'da read ruxsati yo'q yoki papka
     * hali yaratilmagan) — bo'sh ro'yxat qaytaramiz, Pulse panel buzilmaydi.
     *
     * @return Collection<int, object{name: string, size: int, created_at: Carbon}>
     */
    public function all(): Collection
    {
        try {
            $files = $this->disk->files(self::DIR);
        } catch (Throwable $e) {
            Log::warning('Backup papkasini o\'qib bo\'lmadi', [
                'dir' => self::DIR,
                'disk' => (string) config('backup.disk', 'local'),
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        return collect($files)
            ->filter(fn (string $path): bool => $this->isValidName(basename($path)))
            ->map(fn (string $path): object => (object) [
                'name' => basename($path),
                'size' => (int) $this->disk->size($path),
                'created_at' => Carbon::createFromTimestamp($this->disk->lastModified($path)),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    public function exists(string $name): bool
    {
        return $this->isValidName($name) && $this->disk->exists(self::DIR."/{$name}");
    }

    public function download(string $name): StreamedResponse
    {
        return $this->disk->download(self::DIR."/{$name}");
    }

    /**
     * Path traversal himoyasi — faqat tekis *.sql.gz fayl nomi.
     */
    private function isValidName(string $name): bool
    {
        return basename($name) === $name && preg_match(self::NAME_PATTERN, $name) === 1;
    }
}
