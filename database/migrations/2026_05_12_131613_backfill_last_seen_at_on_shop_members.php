<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE shop_members AS sm
            SET last_seen_at = COALESCE(
                (SELECT MAX(o.created_at) FROM orders o WHERE o.member_id = sm.id),
                sm.joined_at
            )
            WHERE sm.last_seen_at IS NULL
        SQL);
    }

    public function down(): void
    {
        // Irreversible — backfilled values cannot be distinguished from real ones.
    }
};
