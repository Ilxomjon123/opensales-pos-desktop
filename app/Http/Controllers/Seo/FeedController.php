<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Response;

final class FeedController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $self = $base.'/feed.xml';
        $now = now()->toRfc2822String();
        $name = (string) config('project.name');
        $domain = (string) parse_url((string) config('project.url'), PHP_URL_HOST);

        $posts = BlogPost::query()
            ->published()
            ->latest('published_at')
            ->limit(50)
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">'."\n".
            "  <channel>\n".
            '    <title>'.htmlspecialchars($name.' Blog').'</title>'."\n".
            '    <link>'.htmlspecialchars($base.'/blog').'</link>'."\n".
            '    <description>'.htmlspecialchars('Distribyutsiya, Telegram bot orqali savdo, qarzdorlik nazorati va FMCG sektoridagi amaliy maqolalar.').'</description>'."\n".
            '    <language>uz-UZ</language>'."\n".
            '    <lastBuildDate>'.$now.'</lastBuildDate>'."\n".
            '    <atom:link href="'.htmlspecialchars($self).'" rel="self" type="application/rss+xml" />'."\n";

        foreach ($posts as $post) {
            $url = $base.'/blog/'.$post->slug;
            $pubDate = $post->published_at?->toRfc2822String() ?? $now;

            $xml .= "    <item>\n";
            $xml .= '      <title>'.htmlspecialchars($post->title).'</title>'."\n";
            $xml .= '      <link>'.htmlspecialchars($url).'</link>'."\n";
            $xml .= '      <guid isPermaLink="true">'.htmlspecialchars($url).'</guid>'."\n";
            $xml .= '      <pubDate>'.$pubDate.'</pubDate>'."\n";
            $xml .= '      <author>noreply@'.$domain.' ('.htmlspecialchars($post->author_name).')</author>'."\n";
            $xml .= '      <description>'.htmlspecialchars($post->excerpt).'</description>'."\n";
            $xml .= '      <content:encoded><![CDATA['.$this->renderBody($post->body).']]></content:encoded>'."\n";
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n</rss>";

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=86400',
        ]);
    }

    private function renderBody(string $body): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($body)) ?: [];

        return implode('', array_map(
            static fn (string $p): string => '<p>'.htmlspecialchars(trim($p)).'</p>',
            $paragraphs,
        ));
    }
}
