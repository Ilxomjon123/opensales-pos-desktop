<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dealer;
use App\Models\MarketplaceBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketplaceBalance>
 */
final class MarketplaceBalanceFactory extends Factory
{
    protected $model = MarketplaceBalance::class;

    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'partner_dealer_id' => Dealer::factory(),
            'balance' => 0,
        ];
    }
}
