<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer\Reports;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class SalesReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Dealer $dealer;

    private Shop $shop;

    private Product $product;

    private ProductCategory $category;

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
        $this->category = ProductCategory::query()->create([
            'dealer_id' => $this->dealer->id,
            'name' => 'Test Category',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->product = Product::factory()
            ->for($this->dealer)
            ->create([
                'category_id' => $this->category->id,
                'name' => 'Test Product',
                'price' => 10_000,
            ]);
    }

    public function test_owner_can_view_sales_report(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.reports.sales.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Reports/Sales')
                ->has('report.summary')
                ->has('report.rows')
                ->has('report.meta.group_by')
                ->has('filters')
                ->has('groupByOptions')
                ->has('statusOptions')
            );
    }

    public function test_report_aggregates_fulfilled_orders_by_default(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $this->createOrder(status: OrderStatus::DELIVERED, deliveredTotal: 100_000, discount: 10_000, createdAt: $today);
        $this->createOrder(status: OrderStatus::RECEIVED, deliveredTotal: 50_000, discount: 0, createdAt: $today);
        // Pending — default holatda hisobga olinmaydi (fulfilled-only).
        $this->createOrder(status: OrderStatus::PENDING, deliveredTotal: 999_999, discount: 0, createdAt: $today);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.sales.index', [
                'date_from' => $today->format('Y-m-d'),
                'date_to' => $today->format('Y-m-d'),
                'group_by' => 'day',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.orders', 2)
                ->where('report.summary.gross', 150_000)
                ->where('report.summary.discount', 10_000)
                ->where('report.summary.net', 140_000)
                ->where('report.summary.aov', 70_000)
            );
    }

    public function test_group_by_product_returns_per_product_rows(): void
    {
        $today = CarbonImmutable::now();
        $order = $this->createOrder(deliveredTotal: 30_000, createdAt: $today);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'qty' => 3,
            'delivered_qty' => 3,
            'price' => 10_000,
            'pack_size' => 1,
            'unit' => 'dona',
        ]);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.sales.index', [
                'date_from' => $today->subDays(1)->format('Y-m-d'),
                'date_to' => $today->addDays(1)->format('Y-m-d'),
                'group_by' => 'product',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.rows.0.label', 'Test Product')
                ->where('report.rows.0.qty', 3)
                ->where('report.rows.0.gross', 30_000)
            );
    }

    public function test_filter_by_shop_excludes_other_shops(): void
    {
        $today = CarbonImmutable::now();
        $otherShop = Shop::factory()->for($this->dealer)->create();

        $this->createOrder(shop: $this->shop, deliveredTotal: 50_000, createdAt: $today);
        $this->createOrder(shop: $otherShop, deliveredTotal: 80_000, createdAt: $today);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.sales.index', [
                'date_from' => $today->subDays(1)->format('Y-m-d'),
                'date_to' => $today->addDays(1)->format('Y-m-d'),
                'group_by' => 'day',
                'shop_id' => $this->shop->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.orders', 1)
                ->where('report.summary.net', 50_000)
            );
    }

    public function test_export_returns_csv_stream(): void
    {
        $today = CarbonImmutable::now();
        $this->createOrder(deliveredTotal: 25_000, createdAt: $today);

        $response = $this->actingAs($this->owner)
            ->get(route('dealer.reports.sales.export', [
                'date_from' => $today->subDays(1)->format('Y-m-d'),
                'date_to' => $today->addDays(1)->format('Y-m-d'),
                'group_by' => 'day',
            ]));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));

        ob_start();
        $response->baseResponse->sendContent();
        $body = (string) ob_get_clean();

        $this->assertStringContainsString('Kesim', $body);
        $this->assertStringContainsString('Buyurtmalar', $body);
    }

    public function test_other_dealer_orders_are_not_included(): void
    {
        $today = CarbonImmutable::now();

        $otherDealer = Dealer::factory()->create();
        $otherShop = Shop::factory()->for($otherDealer)->create();
        Order::factory()
            ->for($otherShop)
            ->create([
                'dealer_id' => $otherDealer->id,
                'status' => OrderStatus::DELIVERED,
                'total' => 999_999,
                'delivered_total' => 999_999,
                'discount' => 0,
            ]);

        $this->createOrder(deliveredTotal: 10_000, createdAt: $today);

        $this->actingAs($this->owner)
            ->get(route('dealer.reports.sales.index', [
                'date_from' => $today->subDays(1)->format('Y-m-d'),
                'date_to' => $today->addDays(1)->format('Y-m-d'),
                'group_by' => 'day',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.orders', 1)
                ->where('report.summary.net', 10_000)
            );
    }

    public function test_invalid_group_by_falls_back_to_day(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dealer.reports.sales.index', ['group_by' => 'invalid']))
            ->assertSessionHasErrors(['group_by']);
    }

    public function test_deliveryman_cannot_access_reports(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $this->actingAs($deliveryman)
            ->get(route('dealer.reports.sales.index'))
            ->assertRedirect();
    }

    private function createOrder(
        ?Shop $shop = null,
        OrderStatus $status = OrderStatus::DELIVERED,
        int $deliveredTotal = 100_000,
        int $discount = 0,
        ?CarbonImmutable $createdAt = null,
    ): Order {
        $shop = $shop ?? $this->shop;
        $when = $createdAt ?? CarbonImmutable::now();

        $order = Order::factory()
            ->for($shop)
            ->create([
                'dealer_id' => $this->dealer->id,
                'status' => $status,
                'total' => $deliveredTotal,
                'delivered_total' => $deliveredTotal,
                'discount' => $discount,
            ]);

        $order->forceFill([
            'created_at' => $when,
            'updated_at' => $when,
        ])->save();

        return $order;
    }
}
