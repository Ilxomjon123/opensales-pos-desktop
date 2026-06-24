<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property ?string $title_ru
 * @property string $excerpt
 * @property ?string $excerpt_ru
 * @property string $body
 * @property ?string $body_ru
 * @property ?string $cover_image
 * @property ?string $meta_title
 * @property ?string $meta_description
 * @property string $author_name
 * @property int $views
 * @property int $read_minutes
 * @property ?Carbon $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'title_ru',
        'excerpt',
        'excerpt_ru',
        'body',
        'body_ru',
        'cover_image',
        'meta_title',
        'meta_description',
        'author_name',
        'views',
        'read_minutes',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views' => 'integer',
        'read_minutes' => 'integer',
    ];

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getUrlAttribute(): string
    {
        $locale = app()->getLocale();

        if ($locale === config('locales.default')) {
            return route('blog.show', $this->slug);
        }

        // Lokalizatsiya qilingan landing havolasi `/{locale}/blog/{slug}`.
        return route('loc.blog.show', ['locale' => $locale, 'post' => $this->slug]);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
