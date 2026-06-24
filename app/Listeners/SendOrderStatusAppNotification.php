<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Mobil ilova feed + FCM push: zakas holati o'zgarganda.
 */
final class SendOrderStatusAppNotification implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->notifications->orderStatus($event->order, $event->to);
    }

    public function failed(OrderStatusChanged $event, Throwable $exception): void
    {
        report($exception);
    }
}
