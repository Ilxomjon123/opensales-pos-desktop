<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProductPriceChanged;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Mobil ilova feed + FCM broadcast: mahsulot narxi o'zgarganda
 * dillerning barcha mijozlariga. Diller sozlamasi (notify_on_price_change) hurmat qilinadi.
 */
final class NotifyPriceChangeApp implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(ProductPriceChanged $event): void
    {
        $dealer = $event->product->loadMissing('dealer')->dealer;

        if ($dealer === null || ! $dealer->notify_on_price_change) {
            return;
        }

        $this->notifications->productPriceChanged($event->product, [
            'old' => $event->oldPrice,
            'new' => $event->newPrice,
            'oldPack' => $event->oldPackPrice,
            'newPack' => $event->newPackPrice,
        ]);
    }

    public function failed(ProductPriceChanged $event, Throwable $exception): void
    {
        report($exception);
    }
}
