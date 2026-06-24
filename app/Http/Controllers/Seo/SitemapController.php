<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $today = now()->toDateString();

        $urls = [
            ['loc' => $base.'/',           'changefreq' => 'weekly',  'priority' => '1.0', 'altRu' => $base.'/?lang=ru'],
            ['loc' => $base.'/#features',  'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $base.'/#audiences', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $base.'/#reports',   'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $base.'/#pricing',   'changefreq' => 'monthly', 'priority' => '0.9'],
            ['loc' => $base.'/#faq',       'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => $base.'/#contact',   'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => $base.'/blog',       'changefreq' => 'weekly',  'priority' => '0.9'],
            ['loc' => $base.'/taqqoslash/opensales-vs-1c',            'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $base.'/taqqoslash/opensales-vs-sales-doctor', 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $base.'/narxlar/kalkulyator',                  'changefreq' => 'monthly', 'priority' => '0.8'],
        ];

        BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at'])
            ->each(function (BlogPost $post) use (&$urls, $base): void {
                $urls[] = [
                    'loc' => $base.'/blog/'.$post->slug,
                    'changefreq' => 'monthly',
                    'priority' => '0.8',
                    'lastmod' => $post->updated_at->toDateString(),
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '.
            'xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($u['loc']).'</loc>'."\n";
            $xml .= '    <lastmod>'.($u['lastmod'] ?? $today).'</lastmod>'."\n";
            $xml .= '    <changefreq>'.$u['changefreq'].'</changefreq>'."\n";
            $xml .= '    <priority>'.$u['priority'].'</priority>'."\n";

            if (! empty($u['altRu'])) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="uz" href="'.htmlspecialchars($u['loc']).'" />'."\n";
                $xml .= '    <xhtml:link rel="alternate" hreflang="ru" href="'.htmlspecialchars($u['altRu']).'" />'."\n";
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="'.htmlspecialchars($u['loc']).'" />'."\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=86400',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
