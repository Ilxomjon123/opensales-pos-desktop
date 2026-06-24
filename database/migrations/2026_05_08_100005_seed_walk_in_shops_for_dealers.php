<?php

declare(strict_types=1);

use App\Enums\ShopType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $dealers = DB::table('dealers')->pluck('id');
        $existing = DB::table('shops')
            ->where('type', ShopType::WALK_IN->value)
            ->pluck('dealer_id')
            ->all();

        $missing = $dealers->reject(fn ($id) => in_array($id, $existing, true));

        foreach ($missing as $dealerId) {
            DB::table('shops')->insert([
                'dealer_id' => $dealerId,
                'type' => ShopType::WALK_IN->value,
                'name' => 'Yo\'lakay xaridor',
                'phone' => null,
                'address' => null,
                'inn' => null,
                'balance' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('shops')->where('type', ShopType::WALK_IN->value)->delete();
    }
};
