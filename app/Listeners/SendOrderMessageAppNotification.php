<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderMessageSent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Mobil ilova feed + FCM push: buyurtmaga yangi xabar kelganda.
 */
final class SendOrderMessageAppNotification implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(OrderMessageSent $event): void
    {
        $this->notifications->orderMessage($event->message);
    }

    public function failed(OrderMessageSent $event, Throwable $exception): void
    {
        report($exception);
    }
}
