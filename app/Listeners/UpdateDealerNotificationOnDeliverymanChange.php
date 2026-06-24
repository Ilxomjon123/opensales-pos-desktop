<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderDeliverymanChanged;
use App\Services\DealerOrderNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class UpdateDealerNotificationOnDeliverymanChange implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly DealerOrderNotificationService $service) {}

    public function handle(OrderDeliverymanChanged $event): void
    {
        $this->service->sendOrUpdate($event->order);
    }

    public function failed(OrderDeliverymanChanged $event, Throwable $exception): void
    {
        report($exception);
    }
}
