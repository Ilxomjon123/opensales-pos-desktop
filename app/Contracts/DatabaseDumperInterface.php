<?php

declare(strict_types=1);

namespace App\Contracts;

interface DatabaseDumperInterface
{
    /**
     * Bazani dump qilib, gzip'langan faylni absolut yo'lga yozadi.
     *
     * @param  array<string, mixed>  $conn  database.connections.* konfiguratsiyasi
     */
    public function dump(array $conn, string $absolutePath): void;
}
