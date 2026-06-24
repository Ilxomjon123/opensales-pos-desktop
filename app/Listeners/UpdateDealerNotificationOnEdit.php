<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderEdited;
use App\Services\DealerOrderNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class UpdateDealerNotificationOnEdit implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly DealerOrderNotificationService $service) {}

    public function handle(OrderEdited $event): void
    {
        $this->service->sendOrUpdate($event->order);
    }

    public function failed(OrderEdited $event, Throwable $exception): void
    {
        report($exception);
    }
}
