<?php

declare(strict_types=1);

namespace App\Livewire\Pulse;

use App\Services\BackupArchive;
use Illuminate\View\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
final class Backups extends Card
{
    public function render(BackupArchive $archive): View
    {
        return view('livewire.pulse.backups', [
            'backups' => $archive->all(),
        ]);
    }
}
