<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\ShopInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopInvite>
 */
final class ShopInviteFactory extends Factory
{
    protected $model = ShopInvite::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'created_by' => User::factory()->state(['role' => UserRole::DELIVERYMAN]),
            'token' => ShopInvite::generateToken(),
            'expires_at' => now()->addHours(ShopInvite::DEFAULT_TTL_HOURS),
            'used_at' => null,
            'used_by_telegram_id' => null,
        ];
    }
}
