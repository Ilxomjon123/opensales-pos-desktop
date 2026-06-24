<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BotVisibility;
use App\Models\Dealer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dealer>
 */
final class DealerFactory extends Factory
{
    protected $model = Dealer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'bot_token' => fake()->numerify('##########:').Str::random(35),
            'bot_username' => fake()->unique()->userName().'_bot',
            'telegram_chat_id' => null,
            'is_active' => true,
            'visibility' => BotVisibility::PRIVATE,
            'webhook_set_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function public(): static
    {
        return $this->state(fn () => ['visibility' => BotVisibility::PUBLIC]);
    }
}
