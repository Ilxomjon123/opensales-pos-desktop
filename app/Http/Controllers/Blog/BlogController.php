<?php

declare(strict_types=1);

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class BlogController extends Controller
{
    public function index(): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->latest('published_at')
            ->paginate(12)
            ->through(fn (BlogPost $post): array => [
                'slug' => $post->slug,
                'title' => $post->title,
                'title_ru' => $post->title_ru,
                'excerpt' => $post->excerpt,
                'excerpt_ru' => $post->excerpt_ru,
                'cover_image' => $post->cover_image,
                'author_name' => $post->author_name,
                'read_minutes' => $post->read_minutes,
                'published_at' => $post->published_at?->toIso8601String(),
                'url' => $post->url,
            ]);

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
        ]);
    }

    public function show(BlogPost $post): Response
    {
        abort_unless($post->published_at !== null && $post->published_at->isPast(), 404);

        DB::table('blog_posts')->where('id', $post->id)->increment('views');

        return Inertia::render('Blog/Show', [
            'post' => [
                'slug' => $post->slug,
                'title' => $post->title,
                'title_ru' => $post->title_ru,
                'excerpt' => $post->excerpt,
                'excerpt_ru' => $post->excerpt_ru,
                'body' => $post->body,
                'body_ru' => $post->body_ru,
                'cover_image' => $post->cover_image,
                'meta_title' => $post->meta_title,
                'meta_description' => $post->meta_description,
                'author_name' => $post->author_name,
                'views' => $post->views,
                'read_minutes' => $post->read_minutes,
                'published_at' => $post->published_at?->toIso8601String(),
                'updated_at' => $post->updated_at->toIso8601String(),
                'url' => $post->url,
            ],
        ]);
    }
}
