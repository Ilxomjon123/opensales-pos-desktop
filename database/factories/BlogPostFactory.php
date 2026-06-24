<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
final class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);
        $body = collect(range(1, 6))
            ->map(fn () => fake()->paragraph(8))
            ->implode("\n\n");

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->randomNumber(5),
            'title' => $title,
            'title_ru' => null,
            'excerpt' => fake()->paragraph(3),
            'excerpt_ru' => null,
            'body' => $body,
            'body_ru' => null,
            'cover_image' => null,
            'meta_title' => Str::limit($title, 60, ''),
            'meta_description' => Str::limit(fake()->paragraph(2), 160, ''),
            'author_name' => 'OpenSales Team',
            'views' => fake()->numberBetween(0, 500),
            'read_minutes' => fake()->numberBetween(3, 10),
            'published_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ];
    }

    public function draft(): self
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function published(): self
    {
        return $this->state(fn () => ['published_at' => now()->subDay()]);
    }
}
