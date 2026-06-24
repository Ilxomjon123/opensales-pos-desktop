<?php

declare(strict_types=1);

namespace Tests\Unit\Dto;

use App\Support\Dto\Cart;
use App\Support\Dto\CartItem;
use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase
{
    public function test_add_item(): void
    {
        $cart = new Cart;
        $item = new CartItem(productId: 1, productName: 'Coca-Cola', price: 5000, qty: 2);

        $newCart = $cart->add($item);

        $this->assertCount(1, $newCart);
        $this->assertSame(10_000, $newCart->total());
        $this->assertCount(0, $cart);
    }

    public function test_add_same_product_merges_qty(): void
    {
        $item1 = new CartItem(productId: 1, productName: 'Coca-Cola', price: 5000, qty: 2);
        $item2 = new CartItem(productId: 1, productName: 'Coca-Cola', price: 5000, qty: 3);

        $cart = (new Cart)->add($item1)->add($item2);

        $this->assertCount(1, $cart);
        $this->assertSame(5, (int) $cart->get(1)?->qty);
        $this->assertSame(25_000, $cart->total());
    }

    public function test_remove_item(): void
    {
        $item = new CartItem(productId: 1, productName: 'Coca-Cola', price: 5000, qty: 2);
        $cart = (new Cart)->add($item)->remove(1);

        $this->assertTrue($cart->isEmpty());
        $this->assertSame(0, $cart->total());
    }

    public function test_from_raw_and_json_serialize(): void
    {
        $raw = [
            ['product_id' => 1, 'product_name' => 'Coca-Cola', 'price' => 5000, 'qty' => 2],
            ['product_id' => 2, 'product_name' => 'Fanta', 'price' => 4500, 'qty' => 1],
        ];

        $cart = Cart::fromRaw($raw);

        $this->assertCount(2, $cart);
        $this->assertSame(14_500, $cart->total());

        $serialized = $cart->jsonSerialize();
        $this->assertCount(2, $serialized);
        $this->assertSame(1, $serialized[0]['product_id']);
    }

    public function test_total_qty(): void
    {
        $cart = (new Cart)
            ->add(new CartItem(1, 'A', 1000, 3))
            ->add(new CartItem(2, 'B', 2000, 5));

        $this->assertSame(8, (int) $cart->totalQty());
    }
}
