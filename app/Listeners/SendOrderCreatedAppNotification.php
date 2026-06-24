<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Mobil ilova feed + FCM push: yangi zakas yaratilganda.
 */
final class SendOrderCreatedAppNotification implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(OrderCreated $event): void
    {
        $this->notifications->orderCreated($event->order);
    }

    public function failed(OrderCreated $event, Throwable $exception): void
    {
        report($exception);
    }
}
