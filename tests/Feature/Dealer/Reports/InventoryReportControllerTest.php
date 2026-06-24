<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer\Reports;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class InventoryReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Dealer $dealer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->owner = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
        $this->product = Product::factory()
            ->for($this->dealer)
            ->create([
                'name' => 'Apple Juice',
                'stock' => 100,
            ]);
    }

    public function test_owner_can_view_inventory_report(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.reports.inventory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Reports/Inventory')
                ->has('report.summary')
                ->has('report.rows')
                ->has('report.meta.date_from')
                ->has('filters')
            );
    }

    public function test_aggregates_movements_in_period(): void
    {
        $today = CarbonImmutable::now();

        $this->makeMovement(TransactionType::STOCK_IN, qty: 50, stockBefore: 50, stockAfter: 100);
        $this->makeMovement(TransactionType::STOCK_OUT, qty: 20, stockBefore: 100, stockAfter: 80);
        $this->makeMovement(TransactionType::SHOP_RETURN, qty: 5, stockBefore: 80, stockAfter: 85);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.inventory.index', [
                'date_from' => $today->subDay()->format('Y-m-d'),
                'date_to' => $today->addDay()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.products', 1)
                ->where('report.summary.in_qty', 50)
                ->where('report.summary.out_qty', 20)
                ->where('report.summary.shop_return_qty', 5)
                ->where('report.summary.net_change', 35)
                ->where('report.rows.0.name', 'Apple Juice')
                ->where('report.rows.0.in_qty', 50)
                ->where('report.rows.0.out_qty', 20)
                ->where('report.rows.0.net_change', 35)
            );
    }

    public function test_filter_by_category_excludes_others(): void
    {
        $today = CarbonImmutable::now();

        $catA = ProductCategory::query()->create([
            'dealer_id' => $this->dealer->id,
            'name' => 'A',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $catB = ProductCategory::query()->create([
            'dealer_id' => $this->dealer->id,
            'name' => 'B',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->product->forceFill(['category_id' => $catA->id])->save();

        $productB = Product::factory()
            ->for($this->dealer)
            ->create(['category_id' => $catB->id, 'name' => 'Other', 'stock' => 10]);

        $this->makeMovement(TransactionType::STOCK_IN, qty: 10, product: $this->product);
        $this->makeMovement(TransactionType::STOCK_IN, qty: 99, product: $productB);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.inventory.index', [
                'date_from' => $today->subDay()->format('Y-m-d'),
                'date_to' => $today->addDay()->format('Y-m-d'),
                'category_id' => $catA->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.products', 1)
                ->where('report.rows.0.name', 'Apple Juice')
            );
    }

    public function test_export_returns_csv_stream(): void
    {
        $this->makeMovement(TransactionType::STOCK_IN, qty: 5);

        $response = $this->actingAs($this->owner)
            ->get(route('dealer.reports.inventory.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_cross_dealer_isolation(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherProduct = Product::factory()->for($otherDealer)->create(['name' => 'Other Product', 'stock' => 100]);

        $this->makeMovement(TransactionType::STOCK_IN, qty: 50, product: $otherProduct, dealerOverride: $otherDealer->id);
        $this->makeMovement(TransactionType::STOCK_IN, qty: 10, product: $this->product);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.inventory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.products', 1)
                ->where('report.rows.0.name', 'Apple Juice')
            );
    }

    public function test_deliveryman_cannot_access_inventory_report(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($deliveryman)
            ->get(route('dealer.reports.inventory.index'))
            ->assertRedirect();
    }

    private function makeMovement(
        TransactionType $type,
        float $qty = 10,
        float $stockBefore = 0,
        float $stockAfter = 0,
        ?Product $product = null,
        ?int $dealerOverride = null,
    ): void {
        $product = $product ?? $this->product;
        $dealerId = $dealerOverride ?? $this->dealer->id;

        $txn = Transaction::factory()->create([
            'dealer_id' => $dealerId,
            'user_id' => $this->owner->id,
            'type' => $type,
        ]);

        TransactionDetail::factory()->create([
            'transaction_id' => $txn->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'qty' => $qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
        ]);
    }
}
