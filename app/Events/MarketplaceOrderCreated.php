<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Marketplace (Birja) kanali orqali yangi buyurtma yaratildi.
 * Shop buyurtmasining OrderCreated'idan farqli — listener'lar shop yuklamaydi.
 */
final class MarketplaceOrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
