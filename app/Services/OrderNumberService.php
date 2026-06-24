<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Diller bo'yicha buyurtmalarga ketma-ket raqam tarqatadi (1, 2, 3, ...).
 *
 * Bir vaqtning o'zida kelgan ikki buyurtma raqamni urishtirib qo'ymasligi uchun
 * yagona atomik `INSERT ... ON CONFLICT DO UPDATE ... RETURNING` operatsiyasi
 * ishlatiladi. Bu PostgreSQL va SQLite ikkalasida ham native qo'llab-quvvatlanadi
 * va row-level lock orqali raqib tranzaksiyalarni avtomatik serial qilib qo'yadi.
 */
final class OrderNumberService
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function nextFor(int $dealerId): int
    {
        $driver = $this->db->getDriverName();
        $now = now()->toDateTimeString();

        $sql = match ($driver) {
            'pgsql', 'sqlite' => <<<'SQL'
                INSERT INTO dealer_order_counters (dealer_id, last_number, created_at, updated_at)
                VALUES (?, 1, ?, ?)
                ON CONFLICT (dealer_id) DO UPDATE
                SET last_number = dealer_order_counters.last_number + 1,
                    updated_at = excluded.updated_at
                RETURNING last_number
                SQL,
            default => throw new RuntimeException("OrderNumberService: driver `{$driver}` qo'llab-quvvatlanmaydi"),
        };

        $row = $this->db->selectOne($sql, [$dealerId, $now, $now]);

        if ($row === null) {
            throw new RuntimeException("OrderNumberService: dealer #{$dealerId} uchun raqam olishda noma'lum xato");
        }

        return (int) (is_array($row) ? $row['last_number'] : $row->last_number);
    }

    /**
     * Diller + oy (YYYY-MM) bo'yicha ketma-ket raqam — har oy 1 dan boshlanadi.
     * nextFor() bilan bir xil atomik UPSERT pattern.
     */
    public function nextMonthlyFor(int $dealerId, string $period): int
    {
        $driver = $this->db->getDriverName();
        $now = now()->toDateTimeString();

        $sql = match ($driver) {
            'pgsql', 'sqlite' => <<<'SQL'
                INSERT INTO dealer_order_month_counters (dealer_id, period, last_number, created_at, updated_at)
                VALUES (?, ?, 1, ?, ?)
                ON CONFLICT (dealer_id, period) DO UPDATE
                SET last_number = dealer_order_month_counters.last_number + 1,
                    updated_at = excluded.updated_at
                RETURNING last_number
                SQL,
            default => throw new RuntimeException("OrderNumberService: driver `{$driver}` qo'llab-quvvatlanmaydi"),
        };

        $row = $this->db->selectOne($sql, [$dealerId, $period, $now, $now]);

        if ($row === null) {
            throw new RuntimeException("OrderNumberService: dealer #{$dealerId} ({$period}) uchun oylik raqam olishda noma'lum xato");
        }

        return (int) (is_array($row) ? $row['last_number'] : $row->last_number);
    }
}
