<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class OgImageController extends Controller
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const CACHE_TTL = 86400;

    public function __invoke(): Response
    {
        $cachePath = storage_path('app/og-image.png');

        if (! file_exists($cachePath) || (time() - filemtime($cachePath) > self::CACHE_TTL)) {
            $data = $this->render();
            @file_put_contents($cachePath, $data);
        } else {
            $data = (string) file_get_contents($cachePath);
        }

        return response($data, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400, s-maxage=604800, immutable',
        ]);
    }

    private function render(): string
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        $this->drawGradient($img, [37, 99, 235], [15, 23, 42]);

        $white = imagecolorallocate($img, 255, 255, 255);
        $accent = imagecolorallocate($img, 96, 165, 250);
        $muted = imagecolorallocatealpha($img, 226, 232, 240, 40);

        $fontPath = public_path('og-font.ttf');
        $name = (string) config('project.name');
        $domain = (string) parse_url((string) config('project.url'), PHP_URL_HOST);

        imagestring($img, 5, 80, 80, strtoupper($name), $accent);

        if (file_exists($fontPath)) {
            imagettftext($img, 56, 0, 80, 280, $white, $fontPath, 'Distribyutorlar uchun');
            imagettftext($img, 56, 0, 80, 360, $white, $fontPath, 'Telegram bot savdo');
            imagettftext($img, 56, 0, 80, 440, $white, $fontPath, 'platformasi');
            imagettftext($img, 28, 0, 80, 540, $muted, $fontPath, 'Katalog • Buyurtma • Marshrut • Hisobotlar');
        } else {
            imagestring($img, 5, 80, 220, 'Distribyutorlar uchun Telegram bot', $white);
            imagestring($img, 5, 80, 260, 'savdo platformasi', $white);
            imagestring($img, 4, 80, 340, 'Katalog | Buyurtma | Marshrut | Hisobotlar', $muted);
        }

        imagestring($img, 4, self::WIDTH - 220, self::HEIGHT - 60, $domain, $white);

        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    /**
     * @param  array{0:int,1:int,2:int}  $top
     * @param  array{0:int,1:int,2:int}  $bottom
     */
    private function drawGradient(\GdImage $img, array $top, array $bottom): void
    {
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $r = (int) ($top[0] + ($bottom[0] - $top[0]) * $y / self::HEIGHT);
            $g = (int) ($top[1] + ($bottom[1] - $top[1]) * $y / self::HEIGHT);
            $b = (int) ($top[2] + ($bottom[2] - $top[2]) * $y / self::HEIGHT);
            $color = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, self::WIDTH, $y, $color);
        }
    }
}
