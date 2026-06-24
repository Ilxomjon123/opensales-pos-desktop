<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IndexNowController extends Controller
{
    public function __invoke(string $key): Response
    {
        $expected = (string) config('services.indexnow.key', '');

        if ($expected === '' || $key !== $expected) {
            throw new NotFoundHttpException;
        }

        return response($expected, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
