<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class ImageOptimizerTest extends TestCase
{
    public function test_encodes_to_webp_and_scales_down_oversize_image(): void
    {
        $file = UploadedFile::fake()->image('big.jpg', 4000, 3000);

        $optimizer = app(ImageOptimizer::class);
        $encoded = $optimizer->encodeFromPath($file->getRealPath());

        $this->assertSame('RIFF', substr($encoded, 0, 4));
        $this->assertSame('WEBP', substr($encoded, 8, 4));

        $info = getimagesizefromstring($encoded);
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(ImageOptimizer::MAX_WIDTH, $info[0]);
        $this->assertLessThanOrEqual(ImageOptimizer::MAX_HEIGHT, $info[1]);
    }

    public function test_does_not_upscale_small_images(): void
    {
        $file = UploadedFile::fake()->image('tiny.jpg', 200, 150);

        $optimizer = app(ImageOptimizer::class);
        $encoded = $optimizer->encodeFromPath($file->getRealPath());

        $info = getimagesizefromstring($encoded);
        $this->assertSame(200, $info[0]);
        $this->assertSame(150, $info[1]);
    }
}
