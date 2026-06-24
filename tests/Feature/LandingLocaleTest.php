<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class LandingLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_home_has_no_prefix_and_uses_uzbek(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->where('locale', 'uz'));
    }

    public function test_prefixed_home_sets_locale_and_cookie(): void
    {
        $response = $this->get('/ru');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->where('locale', 'ru'));

        $response->assertPlainCookie('locale', 'ru');
    }

    public function test_english_landing_subpage_resolves(): void
    {
        $this->get('/en/narxlar/kalkulyator')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));
    }

    public function test_cyrillic_blog_index_resolves(): void
    {
        $this->get('/uz-Cyrl/blog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'uz-Cyrl'));
    }

    public function test_localized_blog_post_resolves_model_binding(): void
    {
        $post = BlogPost::query()->create([
            'slug' => 'test-post',
            'title' => 'Test',
            'excerpt' => 'Excerpt',
            'body' => 'Body',
            'author_name' => 'Author',
            'read_minutes' => 3,
            'published_at' => now()->subDay(),
        ]);

        $this->get('/ru/blog/'.$post->slug)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Blog/Show')
                ->where('locale', 'ru')
                ->where('post.slug', 'test-post'));
    }

    public function test_uz_prefix_is_not_a_landing_url(): void
    {
        // uz default — prefiksiz. `/uz` landing route emas.
        $this->get('/uz')->assertNotFound();
    }

    public function test_unknown_locale_prefix_is_not_matched(): void
    {
        $this->get('/de')->assertNotFound();
    }
}
