<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\DealerOrderNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class SendOrderNotificationToDealer implements ShouldQueue
{
    public int $tries = 1;

    public function __construct(private readonly DealerOrderNotificationService $service) {}

    public function handle(OrderCreated $event): void
    {
        $this->service->sendOrUpdate($event->order);
    }

    public function failed(OrderCreated $event, Throwable $exception): void
    {
        report($exception);
    }
}
