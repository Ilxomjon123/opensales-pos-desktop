<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\OrderStatus;

final class InvalidOrderTransitionException extends DomainException
{
    public static function from(OrderStatus $current, OrderStatus $next): self
    {
        return new self(
            "Buyurtma holatini '{$current->label()}' dan '{$next->label()}' ga o'zgartirib bo'lmaydi"
        );
    }
}
