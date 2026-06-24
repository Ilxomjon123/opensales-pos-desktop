<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProductCreated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Mobil ilova feed + FCM broadcast: yangi mahsulot qo'shilganda
 * dillerning barcha mijozlariga. Diller sozlamasi (notify_on_new_product) hurmat qilinadi.
 */
final class NotifyNewProductApp implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(ProductCreated $event): void
    {
        $dealer = $event->product->loadMissing('dealer')->dealer;

        if ($dealer === null || ! $dealer->notify_on_new_product) {
            return;
        }

        $this->notifications->productCreated($event->product);
    }

    public function failed(ProductCreated $event, Throwable $exception): void
    {
        report($exception);
    }
}
