<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderNumberService;

final class OrderObserver
{
    public function __construct(private readonly OrderNumberService $counter) {}

    public function creating(Order $order): void
    {
        if ($order->number !== null) {
            return;
        }

        if ($order->dealer_id === null) {
            return;
        }

        $dealerId = (int) $order->dealer_id;

        $order->number = $this->counter->nextFor($dealerId);
        $order->month_number = $this->counter->nextMonthlyFor($dealerId, now()->format('Y-m'));
    }
}
