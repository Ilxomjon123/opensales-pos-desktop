<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderEdited;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Mobil ilova feed + FCM push: zakas tahrirlanganda.
 */
final class SendOrderEditedAppNotification implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(OrderEdited $event): void
    {
        $this->notifications->orderEdited($event->order);
    }

    public function failed(OrderEdited $event, Throwable $exception): void
    {
        report($exception);
    }
}
