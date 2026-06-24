<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class HumansController extends Controller
{
    public function __invoke(): Response
    {
        $name = (string) config('project.name');
        $url = rtrim((string) config('project.url'), '/');
        $domain = (string) parse_url((string) config('project.url'), PHP_URL_HOST);

        $content = <<<TXT
/* TEAM */
    Sayt: {$url}
    Loyiha: {$name} — distribyutorlar uchun Telegram bot savdo platformasi
    Joylashuv: Toshkent, O'zbekiston
    Aloqa: hello [at] {$domain}

/* SITE */
    Til: O'zbek, Rus
    Doctype: HTML5
    Standartlar: HTML5, WCAG 2.1 AA

/* TECHNOLOGY */
    Backend: Laravel 13, PHP 8.3+, PostgreSQL 15, Redis 7
    Frontend: Inertia.js v3, Vue 3, Tailwind CSS v4
    Bot: Nutgram (multi-bot)
TXT;

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
