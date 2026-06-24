<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class EmptyCartException extends DomainException
{
    public static function make(): self
    {
        return new self('Savat bo\'sh');
    }
}
