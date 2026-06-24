<?php

declare(strict_types=1);

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BlogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_renders_published_posts(): void
    {
        BlogPost::factory()->published()->create(['title' => 'Published one']);
        BlogPost::factory()->published()->create(['title' => 'Published two']);
        BlogPost::factory()->draft()->create(['title' => 'Draft hidden']);

        $response = $this->withoutVite()->get(route('blog.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Blog/Index')
            ->has('posts.data', 2)
        );
    }

    public function test_blog_show_renders_single_post(): void
    {
        $post = BlogPost::factory()->published()->create([
            'slug' => 'distribyutsiya-haqida',
            'title' => 'Distribyutsiya haqida',
            'body' => 'Asosiy matn shu yerda. Ikkinchi paragraf shu yerda.',
        ]);

        $response = $this->withoutVite()->get(route('blog.show', $post));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Blog/Show')
            ->where('post.slug', 'distribyutsiya-haqida')
            ->where('post.title', 'Distribyutsiya haqida')
        );
    }

    public function test_blog_show_increments_views(): void
    {
        $post = BlogPost::factory()->published()->create(['views' => 0]);

        $this->withoutVite()->get(route('blog.show', $post));

        $this->assertSame(1, $post->fresh()->views);
    }

    public function test_blog_show_returns_404_for_draft(): void
    {
        $post = BlogPost::factory()->draft()->create();

        $this->withoutVite()->get(route('blog.show', $post))->assertNotFound();
    }

    public function test_blog_show_returns_404_for_future_published(): void
    {
        $post = BlogPost::factory()->create(['published_at' => now()->addDay()]);

        $this->withoutVite()->get(route('blog.show', $post))->assertNotFound();
    }

    public function test_sitemap_includes_blog_posts(): void
    {
        $post = BlogPost::factory()->published()->create(['slug' => 'foo-bar-post']);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $this->assertStringContainsString('/blog/foo-bar-post', $response->getContent());
        $this->assertStringContainsString('/blog</loc>', $response->getContent());
    }
}
