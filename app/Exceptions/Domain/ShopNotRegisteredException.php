<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class ShopNotRegisteredException extends DomainException
{
    public static function forTelegramId(int $telegramId): self
    {
        return new self("Mijoz topilmadi: telegram_id={$telegramId}");
    }
}
