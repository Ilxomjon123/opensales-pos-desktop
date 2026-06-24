<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer\Reports;

use App\Enums\ReturnDisposition;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class ReturnsReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Dealer $dealer;

    private Shop $shop;

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
        $this->shop = Shop::factory()->for($this->dealer)->create(['name' => 'Customer A']);
        $this->product = Product::factory()->for($this->dealer)->create(['name' => 'Item A']);
    }

    public function test_owner_can_view_returns_report(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.reports.returns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Reports/Returns')
                ->has('report.summary')
                ->has('report.top_products')
                ->has('report.top_shops')
                ->has('report.by_disposition')
                ->has('filters')
            );
    }

    public function test_aggregates_shop_and_supplier_returns(): void
    {
        $this->makeReturn(TransactionType::SHOP_RETURN, qty: 4, unitCost: 5000, shopId: $this->shop->id);
        $this->makeReturn(TransactionType::SUPPLIER_RETURN, qty: 2, unitCost: 10000);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.returns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.ops_count', 2)
                ->where('report.summary.total_value', 40_000)
                ->where('report.summary.shop_value', 20_000)
                ->where('report.summary.supplier_value', 20_000)
            );
    }

    public function test_disposition_breakdown(): void
    {
        $this->makeReturn(
            TransactionType::SHOP_RETURN,
            qty: 3,
            unitCost: 4000,
            shopId: $this->shop->id,
            disposition: ReturnDisposition::RESTOCK,
        );
        $this->makeReturn(
            TransactionType::SHOP_RETURN,
            qty: 1,
            unitCost: 4000,
            shopId: $this->shop->id,
            disposition: ReturnDisposition::SPOILAGE,
        );

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.returns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.restock_value', 12_000)
                ->where('report.summary.spoilage_value', 4_000)
            );
    }

    public function test_top_shops_lists_returners(): void
    {
        $shopB = Shop::factory()->for($this->dealer)->create(['name' => 'Customer B']);

        $this->makeReturn(TransactionType::SHOP_RETURN, qty: 5, unitCost: 10_000, shopId: $shopB->id);
        $this->makeReturn(TransactionType::SHOP_RETURN, qty: 2, unitCost: 10_000, shopId: $this->shop->id);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.returns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.top_shops.0.name', 'Customer B')
                ->where('report.top_shops.0.value', 50_000)
            );
    }

    public function test_source_filter_returns_only_shop(): void
    {
        $this->makeReturn(TransactionType::SHOP_RETURN, qty: 1, unitCost: 5000, shopId: $this->shop->id);
        $this->makeReturn(TransactionType::SUPPLIER_RETURN, qty: 1, unitCost: 99_999);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.returns.index', ['source' => 'shop_return']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.ops_count', 1)
                ->where('report.summary.total_value', 5_000)
            );
    }

    public function test_export_returns_csv_stream(): void
    {
        $this->makeReturn(TransactionType::SHOP_RETURN, qty: 1, unitCost: 1000, shopId: $this->shop->id);

        $response = $this->actingAs($this->owner)
            ->get(route('dealer.reports.returns.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_cross_dealer_isolation(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherTxn = Transaction::factory()->create([
            'dealer_id' => $otherDealer->id,
            'user_id' => $this->owner->id,
            'type' => TransactionType::SHOP_RETURN,
        ]);
        TransactionDetail::factory()->create([
            'transaction_id' => $otherTxn->id,
            'product_id' => $this->product->id,
            'product_name' => 'hidden',
            'qty' => 100,
            'unit_cost' => 99_999,
        ]);

        $this->makeReturn(TransactionType::SHOP_RETURN, qty: 1, unitCost: 5000, shopId: $this->shop->id);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.returns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.ops_count', 1)
                ->where('report.summary.total_value', 5_000)
            );
    }

    public function test_deliveryman_cannot_access_returns_report(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($deliveryman)
            ->get(route('dealer.reports.returns.index'))
            ->assertRedirect();
    }

    private function makeReturn(
        TransactionType $type,
        float $qty,
        int $unitCost,
        ?int $shopId = null,
        ?ReturnDisposition $disposition = null,
    ): void {
        $txn = Transaction::factory()->create([
            'dealer_id' => $this->dealer->id,
            'user_id' => $this->owner->id,
            'type' => $type,
            'shop_id' => $shopId,
        ]);

        TransactionDetail::factory()->create([
            'transaction_id' => $txn->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'disposition' => $disposition?->value,
        ]);
    }
}
