<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\ReorderProductsAction;
use App\Models\Dealer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReorderProductsActionTest extends TestCase
{
    use RefreshDatabase;

    private ReorderProductsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(ReorderProductsAction::class);
    }

    public function test_assigns_sequential_sort_order_to_provided_ids(): void
    {
        $dealer = Dealer::factory()->create();
        $a = Product::factory()->for($dealer)->create();
        $b = Product::factory()->for($dealer)->create();
        $c = Product::factory()->for($dealer)->create();

        $this->action->execute($dealer->id, [$c->id, $a->id, $b->id]);

        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
    }

    public function test_pushes_remaining_products_after_provided_ones(): void
    {
        $dealer = Dealer::factory()->create();
        $first = Product::factory()->for($dealer)->create();
        $second = Product::factory()->for($dealer)->create();
        $third = Product::factory()->for($dealer)->create();

        // Faqat ikkita ID berildi — uchinchisi avtomatik pastga suriladi.
        $this->action->execute($dealer->id, [$second->id, $first->id]);

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);
    }

    public function test_ignores_ids_from_another_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $other = Dealer::factory()->create();

        $own = Product::factory()->for($dealer)->create();
        $foreign = Product::factory()->for($other)->create(['sort_order' => 99]);

        $this->action->execute($dealer->id, [$foreign->id, $own->id]);

        // Boshqa dealer mahsuloti tegmaydi.
        $this->assertSame(99, $foreign->fresh()->sort_order);
        // O'z mahsuloti birinchi o'ringa o'tadi.
        $this->assertSame(1, $own->fresh()->sort_order);
    }

    public function test_empty_order_is_noop(): void
    {
        $dealer = Dealer::factory()->create();
        $product = Product::factory()->for($dealer)->create(['sort_order' => 5]);

        $this->action->execute($dealer->id, []);

        $this->assertSame(5, $product->fresh()->sort_order);
    }
}
