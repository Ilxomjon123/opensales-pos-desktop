<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OrderMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diller buyurtmaga yangi xabar yuborganda — mobil ilovani xabardor qilish uchun.
 */
final class OrderMessageSent
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly OrderMessage $message) {}
}
