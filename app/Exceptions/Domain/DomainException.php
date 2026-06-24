<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    protected ?string $errorCode = null;

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}
