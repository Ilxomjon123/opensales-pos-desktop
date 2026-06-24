<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class InvalidInviteException extends DomainException
{
    public static function notFound(): self
    {
        return new self('Taklif topilmadi');
    }

    public static function alreadyUsed(): self
    {
        return new self('Bu taklif allaqachon ishlatilgan');
    }

    public static function expired(): self
    {
        return new self('Taklif muddati o\'tgan');
    }
}
