<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OptimizeProductImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_legacy_jpeg_to_webp_and_updates_path(): void
    {
        Storage::fake('public');

        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create();

        $upload = UploadedFile::fake()->image('legacy.jpg', 2000, 1500);
        $oldPath = $upload->storeAs("dealers/{$dealer->id}/products", 'legacy.jpg', 'public');

        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'product_type_id' => null,
            'path' => $oldPath,
            'sort_order' => 0,
        ]);

        $this->artisan('images:optimize-products')->assertSuccessful();

        $image->refresh();
        $this->assertStringEndsWith('.'.ImageOptimizer::EXTENSION, $image->path);
        $this->assertNotSame($oldPath, $image->path);
        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertMissing($oldPath);

        $encoded = Storage::disk('public')->get($image->path);
        $this->assertSame('RIFF', substr($encoded, 0, 4));
        $this->assertSame('WEBP', substr($encoded, 8, 4));

        $info = getimagesizefromstring($encoded);
        $this->assertLessThanOrEqual(ImageOptimizer::MAX_WIDTH, $info[0]);
    }

    public function test_dry_run_does_not_touch_files_or_db(): void
    {
        Storage::fake('public');

        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create();

        $upload = UploadedFile::fake()->image('legacy.jpg', 800, 600);
        $oldPath = $upload->storeAs("dealers/{$dealer->id}/products", 'legacy.jpg', 'public');

        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'product_type_id' => null,
            'path' => $oldPath,
            'sort_order' => 0,
        ]);

        $this->artisan('images:optimize-products', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($oldPath, $image->fresh()->path);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_skips_already_webp_unless_force(): void
    {
        Storage::fake('public');

        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create();

        $optimizer = app(ImageOptimizer::class);
        $upload = UploadedFile::fake()->image('orig.jpg', 1000, 1000);
        $encoded = $optimizer->encodeFromPath($upload->getRealPath());
        $webpPath = "dealers/{$dealer->id}/products/already.webp";
        Storage::disk('public')->put($webpPath, $encoded);

        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'product_type_id' => null,
            'path' => $webpPath,
            'sort_order' => 0,
        ]);

        $this->artisan('images:optimize-products')->assertSuccessful();

        $this->assertSame($webpPath, $image->fresh()->path);
    }
}
