<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\BulkPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class BulkPriceServiceTest extends TestCase
{
    use RefreshDatabase;

    private BulkPriceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BulkPriceService::class);
    }

    public function test_percent_adjust_up(): void
    {
        $dealer = Dealer::factory()->create();
        $p = Product::factory()->for($dealer)->create(['price' => 10_000]);

        $this->service->adjust($dealer->id, [
            'scope' => 'all', 'mode' => 'percent', 'direction' => 'up', 'value' => 10,
        ]);

        $this->assertSame(11_000.0, $p->fresh()->price);
    }

    public function test_percent_adjust_down(): void
    {
        $dealer = Dealer::factory()->create();
        $p = Product::factory()->for($dealer)->create(['price' => 10_000]);

        $this->service->adjust($dealer->id, [
            'scope' => 'all', 'mode' => 'percent', 'direction' => 'down', 'value' => 20,
        ]);

        $this->assertSame(8_000.0, $p->fresh()->price);
    }

    public function test_amount_adjust(): void
    {
        $dealer = Dealer::factory()->create();
        $p = Product::factory()->for($dealer)->create(['price' => 10_000]);

        $this->service->adjust($dealer->id, [
            'scope' => 'all', 'mode' => 'amount', 'direction' => 'up', 'value' => 500,
        ]);

        $this->assertSame(10_500.0, $p->fresh()->price);
    }

    public function test_scope_by_category_only_affects_that_category(): void
    {
        $dealer = Dealer::factory()->create();
        $category = ProductCategory::factory()->for($dealer)->create();

        $inCat = Product::factory()->for($dealer)->create(['price' => 10_000, 'category_id' => $category->id]);
        $outCat = Product::factory()->for($dealer)->create(['price' => 10_000]);

        $this->service->adjust($dealer->id, [
            'scope' => 'category', 'category_id' => $category->id,
            'mode' => 'percent', 'direction' => 'up', 'value' => 10,
        ]);

        $this->assertSame(11_000.0, $inCat->fresh()->price);
        $this->assertSame(10_000.0, $outCat->fresh()->price);
    }

    public function test_dry_run_does_not_modify(): void
    {
        $dealer = Dealer::factory()->create();
        $p = Product::factory()->for($dealer)->create(['price' => 10_000]);

        $result = $this->service->adjust($dealer->id, [
            'scope' => 'all', 'mode' => 'percent', 'direction' => 'up', 'value' => 10,
        ], dryRun: true);

        $this->assertSame(10_000.0, $p->fresh()->price);
        $this->assertSame(11_000.0, $result['preview'][0]['new_price']);
    }

    public function test_price_cannot_go_below_zero_amount_mode(): void
    {
        $dealer = Dealer::factory()->create();
        $p = Product::factory()->for($dealer)->create(['price' => 5_000]);

        $this->service->adjust($dealer->id, [
            'scope' => 'all', 'mode' => 'amount', 'direction' => 'down', 'value' => 10_000,
        ]);

        $this->assertSame(0.0, $p->fresh()->price);
    }

    public function test_csv_import_by_id(): void
    {
        $dealer = Dealer::factory()->create();
        $p1 = Product::factory()->for($dealer)->create(['price' => 1_000]);
        $p2 = Product::factory()->for($dealer)->create(['price' => 2_000]);

        $csv = "id,price\n{$p1->id},1500\n{$p2->id},2500\n";
        $file = UploadedFile::fake()->createWithContent('prices.csv', $csv);

        $result = $this->service->importCsv($dealer->id, $file);

        $this->assertSame(2, $result['updated']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame(1_500.0, $p1->fresh()->price);
        $this->assertSame(2_500.0, $p2->fresh()->price);
    }

    public function test_csv_import_records_not_found(): void
    {
        $dealer = Dealer::factory()->create();
        $p = Product::factory()->for($dealer)->create(['price' => 1_000]);

        $csv = "id,price\n{$p->id},1500\n999999,9999\n";
        $file = UploadedFile::fake()->createWithContent('prices.csv', $csv);

        $result = $this->service->importCsv($dealer->id, $file);

        $this->assertSame(1, $result['updated']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('not_found', $result['skipped'][0]['reason']);
        $this->assertSame('999999', $result['skipped'][0]['value']);
    }

    public function test_csv_import_only_affects_own_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $other = Dealer::factory()->create();
        $mine = Product::factory()->for($dealer)->create(['price' => 1_000]);
        $stranger = Product::factory()->for($other)->create(['price' => 1_000]);

        $csv = "id,price\n{$stranger->id},5000\n";
        $file = UploadedFile::fake()->createWithContent('prices.csv', $csv);

        $result = $this->service->importCsv($dealer->id, $file);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(1_000.0, $mine->fresh()->price);
        $this->assertSame(1_000.0, $stranger->fresh()->price);
    }
}
