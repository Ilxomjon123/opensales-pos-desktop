<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupArchive;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Super admin Pulse paneldan backup faylni yuklab oladi.
 * Admin brauzeri -> server (to'g'ridan-to'g'ri, DPI yo'q).
 */
final class BackupDownloadController extends Controller
{
    public function __construct(private readonly BackupArchive $archive) {}

    public function __invoke(string $file): StreamedResponse
    {
        abort_unless($this->archive->exists($file), 404);

        return $this->archive->download($file);
    }
}
