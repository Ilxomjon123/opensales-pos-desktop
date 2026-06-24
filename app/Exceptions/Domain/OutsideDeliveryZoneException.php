<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class OutsideDeliveryZoneException extends DomainException
{
    public static function make(?string $region, ?string $district): self
    {
        $location = trim(implode(', ', array_filter([$region, $district])));

        $suffix = $location !== '' ? " ({$location})" : '';

        return new self(
            "Bu hudud{$suffix} yetkazib berish zonasidan tashqarida. Iltimos, yetkazib beruvchi bilan bog'laning."
        );
    }
}
