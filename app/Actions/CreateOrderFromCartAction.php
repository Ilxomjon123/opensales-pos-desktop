<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Order;
use App\Models\Shop;
use App\Services\OrderService;

final class CreateOrderFromCartAction
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function execute(Shop $shop, array $cartItems): Order
    {
        return $this->orderService->createFromCart($shop, $cartItems);
    }
}
