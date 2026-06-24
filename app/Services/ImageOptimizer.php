<?php

declare(strict_types=1);

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

final class ImageOptimizer
{
    public const MAX_WIDTH = 1280;

    public const MAX_HEIGHT = 1280;

    public const QUALITY = 82;

    public const THUMB_SIZE = 300;

    public const THUMB_QUALITY = 75;

    public const EXTENSION = 'webp';

    private readonly ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Rasm faylini WebP formatga aylantirib, max o'lcham bo'yicha kichraytiradi.
     */
    public function encodeFromPath(string $sourcePath): string
    {
        $image = $this->manager->decodePath($sourcePath);

        $image->scaleDown(width: self::MAX_WIDTH, height: self::MAX_HEIGHT);

        return (string) $image->encode(new WebpEncoder(quality: self::QUALITY));
    }

    /**
     * 300×300 sqr thumbnail (cover crop). Katalog ko'rinishi va Telegram preview
     * uchun — to'liq rasmni qayta yuklamasdan tez ko'rsatish.
     */
    public function encodeThumbnail(string $sourcePath): string
    {
        $image = $this->manager->decodePath($sourcePath);

        $image->cover(self::THUMB_SIZE, self::THUMB_SIZE);

        return (string) $image->encode(new WebpEncoder(quality: self::THUMB_QUALITY));
    }

    /**
     * Bitta o'qishda full + thumb ikkalasini ham qaytaradi (decode bir marta).
     *
     * @return array{full: string, thumb: string}
     */
    public function encodeWithThumb(string $sourcePath): array
    {
        $image = $this->manager->decodePath($sourcePath);

        $full = (clone $image)->scaleDown(width: self::MAX_WIDTH, height: self::MAX_HEIGHT);
        $thumb = $image->cover(self::THUMB_SIZE, self::THUMB_SIZE);

        return [
            'full' => (string) $full->encode(new WebpEncoder(quality: self::QUALITY)),
            'thumb' => (string) $thumb->encode(new WebpEncoder(quality: self::THUMB_QUALITY)),
        ];
    }
}
