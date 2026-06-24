<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductUnit: string
{
    case DONA = 'dona';
    case KG = 'kg';

    public function label(): string
    {
        return (string) __('enums.ProductUnit.'.$this->value);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
