<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Offline POS desktop — barcha event listenerlar tashqi xizmatlar uchun
 * (Telegram bot, Firebase push, marketplace). Internetsiz ishlaganda ular
 * kerak emas va xato beradi. Shuning uchun avtomatik listener discovery
 * o'chirilgan va hech qanday listener ro'yxatdan o'tmaydi. POS sotuvning
 * o'zi (stok, moliya, status tarixi) PosSaleService ichida inline bajariladi.
 */
final class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
