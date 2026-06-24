<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Services\CommissionHistoryService;

final class InvalidateCommissionHistoryCache
{
    public function __construct(
        private readonly CommissionHistoryService $history,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to === OrderStatus::DELIVERED || $event->from === OrderStatus::DELIVERED) {
            $this->history->invalidate();
        }
    }
}
