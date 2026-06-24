<?php

declare(strict_types=1);

namespace Tests\Feature\Dealer;

use App\Enums\UserRole;
use App\Events\ProductCreated;
use App\Events\ProductPriceChanged;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Dealer $dealer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dealer = Dealer::factory()->create();
        $this->user = User::factory()->create([
            'role' => UserRole::DEALER,
            'dealer_id' => $this->dealer->id,
        ]);
    }

    public function test_dealer_can_list_products(): void
    {
        Product::factory()->for($this->dealer)->count(5)->create();

        $this->actingAs($this->user)
            ->get(route('dealer.products.index'))
            ->assertOk();
    }

    public function test_index_reports_total_stock_value(): void
    {
        Product::factory()->for($this->dealer)->create([
            'has_types' => false, 'stock' => 10, 'price' => 5_000,
        ]);
        Product::factory()->for($this->dealer)->create([
            'has_types' => false, 'stock' => 3, 'price' => 20_000,
        ]);

        $this->actingAs($this->user)
            ->get(route('dealer.products.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stockValue', 110_000) // 10*5000 + 3*20000
            );
    }

    public function test_index_ignores_negative_stock_in_total_value(): void
    {
        Product::factory()->for($this->dealer)->create([
            'has_types' => false, 'stock' => 10, 'price' => 5_000,
        ]);
        // Manfiy qoldiq (ortiqcha sotilgan) summaga ta'sir qilmasligi kerak.
        Product::factory()->for($this->dealer)->create([
            'has_types' => false, 'stock' => -4, 'price' => 20_000,
        ]);

        $this->actingAs($this->user)
            ->get(route('dealer.products.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stockValue', 50_000) // 10*5000 + 0 (manfiy e'tiborsiz)
            );
    }

    public function test_dealer_can_create_product(): void
    {
        $category = ProductCategory::factory()->for($this->dealer)->create();

        $this->actingAs($this->user)
            ->post(route('dealer.products.store'), [
                'name' => 'Coca-Cola 1L',
                'price' => 12_000,
                'stock' => 100,
                'pack_size' => 1,
                'unit' => 'dona',
                'category_id' => $category->id,
            ])
            ->assertRedirect(route('dealer.products.index'));

        $this->assertDatabaseHas('products', [
            'dealer_id' => $this->dealer->id,
            'name' => 'Coca-Cola 1L',
            'price' => 12_000,
        ]);
    }

    public function test_creating_product_dispatches_product_created_event(): void
    {
        Event::fake([ProductCreated::class]);

        $this->actingAs($this->user)
            ->post(route('dealer.products.store'), [
                'name' => 'Pepsi 1L',
                'price' => 11_000,
                'stock' => 50,
                'pack_size' => 1,
                'unit' => 'dona',
            ])
            ->assertRedirect(route('dealer.products.index'));

        Event::assertDispatched(
            ProductCreated::class,
            fn (ProductCreated $e): bool => $e->product->name === 'Pepsi 1L'
                && $e->product->dealer_id === $this->dealer->id,
        );
    }

    public function test_dealer_can_update_own_product(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['name' => 'Old Name']);

        $this->actingAs($this->user)
            ->put(route('dealer.products.update', $product), [
                'name' => 'New Name',
                'price' => 15_000,
            ])
            ->assertRedirect(route('dealer.products.index'));

        $this->assertSame('New Name', $product->fresh()->name);
    }

    public function test_updating_price_dispatches_price_changed_event(): void
    {
        Event::fake([ProductPriceChanged::class]);

        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000]);

        $this->actingAs($this->user)
            ->put(route('dealer.products.update', $product), [
                'name' => $product->name,
                'price' => 12_000,
            ])
            ->assertRedirect(route('dealer.products.index'));

        Event::assertDispatched(
            ProductPriceChanged::class,
            fn (ProductPriceChanged $e): bool => $e->product->is($product)
                && $e->oldPrice === 10_000.0
                && $e->newPrice === 12_000.0,
        );
    }

    public function test_updating_without_price_change_does_not_dispatch_event(): void
    {
        Event::fake([ProductPriceChanged::class]);

        $product = Product::factory()->for($this->dealer)->create(['price' => 10_000, 'name' => 'Old']);

        $this->actingAs($this->user)
            ->put(route('dealer.products.update', $product), [
                'name' => 'Renamed',
                'price' => 10_000,
            ])
            ->assertRedirect(route('dealer.products.index'));

        Event::assertNotDispatched(ProductPriceChanged::class);
    }

    public function test_dealer_cannot_update_others_product(): void
    {
        $otherDealer = Dealer::factory()->create();
        $product = Product::factory()->for($otherDealer)->create();

        $this->actingAs($this->user)
            ->put(route('dealer.products.update', $product), ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_dealer_can_view_reorder_page(): void
    {
        Product::factory()->for($this->dealer)->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('dealer.products.reorder'))
            ->assertOk();
    }

    public function test_dealer_can_save_new_product_order(): void
    {
        $a = Product::factory()->for($this->dealer)->create();
        $b = Product::factory()->for($this->dealer)->create();
        $c = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->user)
            ->postJson(route('dealer.products.reorder.update'), [
                'order' => [$c->id, $a->id, $b->id],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
    }

    public function test_reorder_rejects_ids_from_another_dealer(): void
    {
        $otherDealer = Dealer::factory()->create();
        $foreign = Product::factory()->for($otherDealer)->create();

        $this->actingAs($this->user)
            ->postJson(route('dealer.products.reorder.update'), [
                'order' => [$foreign->id],
            ])
            ->assertStatus(422);
    }

    public function test_warehouse_can_reorder_products(): void
    {
        $warehouse = User::factory()->create([
            'role' => UserRole::WAREHOUSE,
            'dealer_id' => $this->dealer->id,
        ]);

        $a = Product::factory()->for($this->dealer)->create();
        $b = Product::factory()->for($this->dealer)->create();

        $this->actingAs($warehouse)
            ->get(route('dealer.products.reorder'))
            ->assertOk();

        $this->actingAs($warehouse)
            ->postJson(route('dealer.products.reorder.update'), [
                'order' => [$b->id, $a->id],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }

    public function test_deliveryman_cannot_reorder_products(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);

        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($deliveryman)
            ->get(route('dealer.products.reorder'))
            ->assertRedirect();

        $this->actingAs($deliveryman)
            ->post(route('dealer.products.reorder.update'), [
                'order' => [$product->id],
            ])
            ->assertRedirect();
    }

    public function test_dealer_can_delete_product(): void
    {
        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->user)
            ->delete(route('dealer.products.destroy', $product))
            ->assertRedirect(route('dealer.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_dealer_can_filter_by_search(): void
    {
        Product::factory()->for($this->dealer)->create(['name' => 'Coca-Cola']);
        Product::factory()->for($this->dealer)->create(['name' => 'Fanta']);

        $this->actingAs($this->user)
            ->get(route('dealer.products.index', ['search' => 'coca']))
            ->assertOk();
    }

    public function test_dealer_can_filter_products_with_types(): void
    {
        $withTypes = Product::factory()->for($this->dealer)->create(['name' => 'Assortimentli', 'has_types' => true]);
        Product::factory()->for($this->dealer)->create(['name' => 'Assortimentsiz', 'has_types' => false]);

        $this->actingAs($this->user)
            ->get(route('dealer.products.index', ['has_types' => 'with']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $withTypes->id)
            );
    }

    public function test_dealer_can_filter_products_without_types(): void
    {
        Product::factory()->for($this->dealer)->create(['name' => 'Assortimentli', 'has_types' => true]);
        $withoutTypes = Product::factory()->for($this->dealer)->create(['name' => 'Assortimentsiz', 'has_types' => false]);

        $this->actingAs($this->user)
            ->get(route('dealer.products.index', ['has_types' => 'without']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dealer/Products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $withoutTypes->id)
            );
    }

    public function test_dealer_can_reorder_existing_images_to_set_primary(): void
    {
        Storage::fake('public');

        $product = Product::factory()->for($this->dealer)->create();
        $img1 = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/a.jpg', 'sort_order' => 0]);
        $img2 = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/b.jpg', 'sort_order' => 1]);
        $img3 = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/c.jpg', 'sort_order' => 2]);

        $this->actingAs($this->user)
            ->put(route('dealer.products.update', $product), [
                'name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock,
                'pack_size' => $product->pack_size,
                'unit' => $product->unit->value,
                'image_order' => ["ex:{$img3->id}", "ex:{$img1->id}", "ex:{$img2->id}"],
            ])
            ->assertRedirect(route('dealer.products.index'));

        $this->assertSame(0, $img3->fresh()->sort_order);
        $this->assertSame(1, $img1->fresh()->sort_order);
        $this->assertSame(2, $img2->fresh()->sort_order);
    }

    public function test_dealer_can_make_uploaded_image_primary_via_image_order(): void
    {
        Storage::fake('public');

        $product = Product::factory()->for($this->dealer)->create();
        $existing = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/a.jpg', 'sort_order' => 0]);

        $this->actingAs($this->user)
            ->put(route('dealer.products.update', $product), [
                'name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock,
                'pack_size' => $product->pack_size,
                'unit' => $product->unit->value,
                'images' => [UploadedFile::fake()->image('new.jpg')],
                'image_order' => ['new:0', "ex:{$existing->id}"],
            ])
            ->assertRedirect(route('dealer.products.index'));

        $images = $product->fresh()->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        $this->assertSame(0, $images[0]->sort_order);
        $this->assertNotSame($existing->id, $images[0]->id);
        $this->assertSame($existing->id, $images[1]->id);
    }

    public function test_dealer_can_remove_and_reorder_images_at_once(): void
    {
        Storage::fake('public');

        $product = Product::factory()->for($this->dealer)->create();
        $keep = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/a.jpg', 'sort_order' => 0]);
        $drop = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/b.jpg', 'sort_order' => 1]);
        $other = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/c.jpg', 'sort_order' => 2]);

        $this->actingAs($this->user)
            ->put(route('dealer.products.update', $product), [
                'name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock,
                'pack_size' => $product->pack_size,
                'unit' => $product->unit->value,
                'remove_image_ids' => [$drop->id],
                'image_order' => ["ex:{$other->id}", "ex:{$keep->id}"],
            ])
            ->assertRedirect(route('dealer.products.index'));

        $this->assertDatabaseMissing('product_images', ['id' => $drop->id]);
        $this->assertSame(0, $other->fresh()->sort_order);
        $this->assertSame(1, $keep->fresh()->sort_order);
    }

    public function test_dealer_can_toggle_product_active(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patch(route('dealer.products.toggle', $product))
            ->assertRedirect();

        $this->assertFalse($product->fresh()->is_active);

        $this->actingAs($this->user)
            ->patch(route('dealer.products.toggle', $product))
            ->assertRedirect();

        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_toggle_returns_json_for_ajax_requests(): void
    {
        $product = Product::factory()->for($this->dealer)->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patchJson(route('dealer.products.toggle', $product))
            ->assertOk()
            ->assertExactJson(['is_active' => false]);

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_warehouse_can_toggle_product_active(): void
    {
        $warehouse = User::factory()->create([
            'role' => UserRole::WAREHOUSE,
            'dealer_id' => $this->dealer->id,
        ]);
        $product = Product::factory()->for($this->dealer)->create(['is_active' => true]);

        $this->actingAs($warehouse)
            ->patch(route('dealer.products.toggle', $product))
            ->assertRedirect();

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_deliveryman_cannot_toggle_product_active(): void
    {
        $deliveryman = User::factory()->create([
            'role' => UserRole::DELIVERYMAN,
            'dealer_id' => $this->dealer->id,
        ]);
        $product = Product::factory()->for($this->dealer)->create(['is_active' => true]);

        $this->actingAs($deliveryman)
            ->patch(route('dealer.products.toggle', $product))
            ->assertRedirect(route('dealer.routes.today'));

        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_dealer_cannot_toggle_other_dealer_product(): void
    {
        $otherDealer = Dealer::factory()->create();
        $product = Product::factory()->for($otherDealer)->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patch(route('dealer.products.toggle', $product))
            ->assertForbidden();

        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_image_order_rejects_invalid_tokens(): void
    {
        $product = Product::factory()->for($this->dealer)->create();

        $this->actingAs($this->user)
            ->put(route('dealer.products.update', $product), [
                'name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock,
                'pack_size' => $product->pack_size,
                'unit' => $product->unit->value,
                'image_order' => ['bogus:1'],
            ])
            ->assertSessionHasErrors('image_order.0');
    }
}
