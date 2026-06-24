<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class SeoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_returns_xml_with_hreflang(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('X-Robots-Tag', 'noindex');

        $body = $response->getContent();
        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString('hreflang="uz"', $body);
        $this->assertStringContainsString('hreflang="ru"', $body);
        $this->assertStringContainsString('hreflang="x-default"', $body);
    }

    public function test_llms_txt_serves_plaintext_brief(): void
    {
        $response = $this->get(route('llms'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $this->assertStringContainsString('OpenSales', $response->getContent());
    }

    public function test_humans_txt_serves_team_info(): void
    {
        $response = $this->get(route('humans'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $this->assertStringContainsString('TEAM', $response->getContent());
    }

    public function test_security_txt_serves_contact_with_expiry(): void
    {
        $response = $this->get(route('security.txt'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString('Contact:', $body);
        $this->assertStringContainsString('Expires:', $body);
    }

    public function test_og_image_returns_png_binary(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded.');
        }

        $response = $this->get(route('og.image'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertGreaterThan(1000, strlen((string) $response->getContent()));
    }

    public function test_feed_xml_returns_rss_with_published_posts(): void
    {
        BlogPost::factory()->published()->create([
            'slug' => 'rss-test-post',
            'title' => 'RSS sinov maqolasi',
            'excerpt' => 'Qisqa tavsif',
        ]);

        $response = $this->get(route('feed'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString('<rss version="2.0"', $body);
        $this->assertStringContainsString('RSS sinov maqolasi', $body);
        $this->assertStringContainsString('/blog/rss-test-post', $body);
    }

    public function test_indexnow_returns_404_when_key_missing(): void
    {
        Config::set('services.indexnow.key', null);

        $this->get('/'.str_repeat('a', 32).'.txt')->assertNotFound();
    }

    public function test_indexnow_returns_key_when_match(): void
    {
        $key = str_repeat('a', 32);
        Config::set('services.indexnow.key', $key);

        $response = $this->get("/{$key}.txt");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $this->assertSame($key, $response->getContent());
    }
}
