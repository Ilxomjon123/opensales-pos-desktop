<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class SecurityTxtController extends Controller
{
    public function __invoke(): Response
    {
        $expires = now()->addYear()->format('Y-m-d\TH:i:s\Z');
        $domain = (string) parse_url((string) config('project.url'), PHP_URL_HOST);
        $url = rtrim((string) config('project.url'), '/');

        $content = <<<TXT
Contact: mailto:security@{$domain}
Expires: {$expires}
Preferred-Languages: uz, ru, en
Canonical: {$url}/.well-known/security.txt
TXT;

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
