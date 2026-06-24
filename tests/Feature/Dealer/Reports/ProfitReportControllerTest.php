<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer\Reports;

use App\Actions\RecordStockTransactionAction;
use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Dto\Cart;
use App\Support\Dto\CartItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class ProfitReportControllerTest extends TestCase
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
        $this->shop = Shop::factory()->for($this->dealer)->create();
        $this->product = Product::factory()
            ->for($this->dealer)
            ->create([
                'name' => 'Sample Product',
                'price' => 12_000,
                'stock' => 50,
            ]);
    }

    public function test_owner_can_view_profit_report(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.reports.profit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Reports/Profit')
                ->has('report.summary')
                ->has('report.rows')
                ->has('filters')
            );
    }

    public function test_computes_margin_using_order_item_cost_snapshot(): void
    {
        // Sotuv paytida tannarx 8800 edi.
        $this->makeSale(deliveredQty: 10, price: 12_000, unitCost: 8_800);

        $today = CarbonImmutable::now();

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.profit.index', [
                'date_from' => $today->subDay()->format('Y-m-d'),
                'date_to' => $today->addDay()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.products', 1)
                ->where('report.summary.revenue', 120_000)
                ->where('report.summary.cogs', 88_000)
                ->where('report.summary.profit', 32_000)
                ->where('report.rows.0.avg_cost', 8_800)
                ->where('report.rows.0.has_cost', true)
            );
    }

    public function test_historical_margin_stays_stable_when_current_cost_changes(): void
    {
        // Yanvarda 5000 so'm tannarxda sotildi.
        $this->makeSale(deliveredQty: 10, price: 12_000, unitCost: 5_000);

        // Mart oyida tannarx 8000 ga oshdi — products.cost_price yangilandi,
        // lekin eski order_items.unit_cost o'zgarmasligi kerak.
        $this->product->forceFill(['cost_price' => 8_000])->save();

        $today = CarbonImmutable::now();

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.profit.index', [
                'date_from' => $today->subDay()->format('Y-m-d'),
                'date_to' => $today->addDay()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.revenue', 120_000)
                ->where('report.summary.cogs', 50_000) // 10 × 5000 (snapshot saqlangan)
                ->where('report.summary.profit', 70_000)
                ->where('report.rows.0.avg_cost', 5_000)
            );
    }

    public function test_order_creation_snapshots_current_cost_price(): void
    {
        // Yangi mahsulot uchun tannarx kiritildi.
        $this->product->forceFill(['cost_price' => 4_500])->save();

        // Mahsulot omborda mavjud bo'lishi kerak.
        $this->product->forceFill(['stock' => 100])->save();

        $cart = (new Cart)->add(new CartItem(
            productId: $this->product->id,
            productName: $this->product->name,
            price: 10_000,
            qty: 3,
            packSize: 1,
        ));

        $service = app(OrderService::class);
        $order = $service->createFromCart($this->shop, $cart);

        $item = $order->items->first();
        $this->assertSame(4500.0, (float) $item->unit_cost);

        // Tannarx o'zgarsa snapshot saqlanadi.
        $this->product->forceFill(['cost_price' => 9_999])->save();
        $item->refresh();
        $this->assertSame(4500.0, (float) $item->unit_cost);
    }

    public function test_stock_in_auto_updates_product_cost_price(): void
    {
        $action = app(RecordStockTransactionAction::class);
        $supplier = Supplier::factory()->for($this->dealer)->create();

        // Eski tannarx
        $this->product->forceFill(['cost_price' => 5_000])->save();

        // Yangi STOCK_IN — narx oshdi: 7500 so'm
        $action->execute(
            actor: $this->owner,
            dealerId: $this->dealer->id,
            type: TransactionType::STOCK_IN,
            lines: [
                ['product_id' => $this->product->id, 'qty' => 10, 'unit_cost' => 7_500],
            ],
            supplierId: $supplier->id,
        );

        // Mahsulot tannarxi avto-yangilangan bo'lishi kerak.
        $this->product->refresh();
        $this->assertSame(7500.0, (float) $this->product->cost_price);
    }

    public function test_product_without_cost_flagged(): void
    {
        $this->makeSale(deliveredQty: 5, price: 10_000);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.profit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.products', 1)
                ->where('report.summary.products_without_cost', 1)
                ->where('report.rows.0.has_cost', false)
                ->where('report.rows.0.cogs', 0)
                ->where('report.rows.0.profit', 50_000)
            );
    }

    public function test_export_returns_csv_stream(): void
    {
        $this->makeSale(deliveredQty: 1, price: 10_000);

        $response = $this->actingAs($this->owner)
            ->get(route('dealer.reports.profit.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_cross_dealer_isolation(): void
    {
        $otherDealer = Dealer::factory()->create();
        $otherShop = Shop::factory()->for($otherDealer)->create();
        $otherProduct = Product::factory()->for($otherDealer)->create(['price' => 50_000]);

        $order = Order::factory()
            ->for($otherShop)
            ->create([
                'dealer_id' => $otherDealer->id,
                'status' => OrderStatus::DELIVERED,
                'total' => 50_000,
                'delivered_total' => 50_000,
                'discount' => 0,
            ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $otherProduct->id,
            'product_name' => $otherProduct->name,
            'qty' => 1,
            'delivered_qty' => 1,
            'price' => 50_000,
            'pack_size' => 1,
            'unit' => 'dona',
        ]);

        $this->makeSale(deliveredQty: 1, price: 10_000);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.profit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.products', 1)
                ->where('report.rows.0.name', 'Sample Product')
            );
    }

    public function test_deliveryman_cannot_access_profit_report(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($deliveryman)
            ->get(route('dealer.reports.profit.index'))
            ->assertRedirect();
    }

    private function makeSale(float $deliveredQty, int $price, ?int $unitCost = null): Order
    {
        $order = Order::factory()
            ->for($this->shop)
            ->create([
                'dealer_id' => $this->dealer->id,
                'status' => OrderStatus::DELIVERED,
                'total' => $price * (int) $deliveredQty,
                'delivered_total' => $price * (int) $deliveredQty,
                'discount' => 0,
            ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'qty' => $deliveredQty,
            'delivered_qty' => $deliveredQty,
            'price' => $price,
            'unit_cost' => $unitCost,
            'pack_size' => 1,
            'unit' => 'dona',
        ]);

        return $order;
    }
}
