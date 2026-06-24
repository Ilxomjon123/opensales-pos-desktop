<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * DB-driverga mos YYYY-MM grouping ifodasi.
 * Komissiya hisoblash xizmatlarida ishlatiladi.
 */
final class SqlMonth
{
    public static function expression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => throw new \RuntimeException("Unsupported DB driver: {$driver}"),
        };
    }
}
