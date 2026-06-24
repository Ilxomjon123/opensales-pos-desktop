<?php

declare(strict_types=1);

return [
    // Backup fayllari saqlanadigan storage disk
    'disk' => env('BACKUP_DISK', 'local'),

    // Necha kun saqlanadi (retention)
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
];
