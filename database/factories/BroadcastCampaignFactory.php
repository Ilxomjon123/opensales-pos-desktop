<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastScheduleType;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastCampaign>
 */
final class BroadcastCampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'created_by_user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'message_text' => 'Assalomu alaykum, {shop_name}!',
            'media_path' => null,
            'media_type' => null,
            'buttons' => null,
            'audience_type' => BroadcastAudienceType::ALL_ACTIVE->value,
            'audience_config' => null,
            'schedule_type' => BroadcastScheduleType::DAILY->value,
            'schedule_config' => ['time' => '09:00'],
            'timezone' => 'Asia/Tashkent',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'last_run_at' => null,
            'next_run_at' => null,
        ];
    }

    public function once(string $datetime): self
    {
        return $this->state(fn (): array => [
            'schedule_type' => BroadcastScheduleType::ONCE->value,
            'schedule_config' => ['datetime' => $datetime],
        ]);
    }

    public function daily(string $time = '09:00'): self
    {
        return $this->state(fn (): array => [
            'schedule_type' => BroadcastScheduleType::DAILY->value,
            'schedule_config' => ['time' => $time],
        ]);
    }

    public function weekly(array $days = [1, 3, 5], string $time = '09:00'): self
    {
        return $this->state(fn (): array => [
            'schedule_type' => BroadcastScheduleType::WEEKLY->value,
            'schedule_config' => ['days' => $days, 'time' => $time],
        ]);
    }

    public function platformLevel(): self
    {
        return $this->state(fn (): array => [
            'dealer_id' => null,
            'audience_type' => BroadcastAudienceType::PLATFORM_DEALERS->value,
        ]);
    }
}
