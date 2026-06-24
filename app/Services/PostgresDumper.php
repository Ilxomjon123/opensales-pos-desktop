<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DatabaseDumperInterface;
use Symfony\Component\Process\Process;

final class PostgresDumper implements DatabaseDumperInterface
{
    // Uzun bazalar uchun timeout (soniya)
    private const TIMEOUT = 3600.0;

    /**
     * pg_dump | gzip — parolni PGPASSWORD env orqali uzatamiz (CLI'da ko'rinmaydi).
     *
     * @param  array<string, mixed>  $conn  database.connections.* konfiguratsiyasi
     */
    public function dump(array $conn, string $absolutePath): void
    {
        $command = sprintf(
            'pg_dump -h %s -p %s -U %s -d %s -Fc | gzip > %s',
            escapeshellarg((string) $conn['host']),
            escapeshellarg((string) $conn['port']),
            escapeshellarg((string) $conn['username']),
            escapeshellarg((string) $conn['database']),
            escapeshellarg($absolutePath),
        );

        $process = Process::fromShellCommandline($command, null, [
            'PGPASSWORD' => (string) ($conn['password'] ?? ''),
        ]);
        $process->setTimeout(self::TIMEOUT);
        $process->mustRun();
    }
}
