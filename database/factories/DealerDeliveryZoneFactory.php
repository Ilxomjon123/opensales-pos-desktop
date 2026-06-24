<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dealer;
use App\Models\DealerDeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealerDeliveryZone>
 */
final class DealerDeliveryZoneFactory extends Factory
{
    protected $model = DealerDeliveryZone::class;

    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'region' => 'Toshkent shahri',
            'district' => 'Chilonzor tumani',
        ];
    }

    /**
     * Butun viloyat (barcha tumanlar).
     */
    public function wholeRegion(): static
    {
        return $this->state(fn () => ['district' => null]);
    }
}
