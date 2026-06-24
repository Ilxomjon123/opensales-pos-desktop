<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Order;
use App\Models\PosShift;
use App\Models\Shop;
use App\Models\User;
use App\Services\PosSaleService;

final class CreatePosSaleAction
{
    public function __construct(
        private readonly PosSaleService $sales,
    ) {}

    /**
     * @param  array<int, array{product_id:int, product_type_id?:?int, qty:int|float, pack_qty?:?int, price?:int|float|null, pack_price?:int|float|null}>  $items
     */
    public function execute(
        PosShift $shift,
        User $cashier,
        Shop $customer,
        array $items,
        int $paidCash,
        int $paidCard,
        int $discount = 0,
        ?string $cardholderName = null,
        ?string $note = null,
    ): Order {
        return $this->sales->create(
            shift: $shift,
            cashier: $cashier,
            customer: $customer,
            items: $items,
            paidCash: $paidCash,
            paidCard: $paidCard,
            discount: $discount,
            cardholderName: $cardholderName,
            note: $note,
        );
    }
}
