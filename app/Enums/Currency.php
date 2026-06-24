<?php

declare(strict_types=1);

namespace App\Enums;

enum Currency: string
{
    case UZS = 'UZS';
    case RUB = 'RUB';

    /**
     * Pul yonida ko'rsatiladigan belgi/qisqartma (lokalizatsiya qilingan).
     */
    public function symbol(): string
    {
        return (string) __('enums.Currency.symbol.'.$this->value);
    }

    public function label(): string
    {
        return (string) __('enums.Currency.'.$this->value);
    }

    /**
     * Raqamlarni guruhlash uchun lokal (Intl.NumberFormat / number_format).
     */
    public function numberLocale(): string
    {
        return match ($this) {
            self::UZS => 'ru-RU',
            self::RUB => 'ru-RU',
        };
    }

    /**
     * Kasr birliklarisiz (so'm/rubl butun sonlarda yuritiladi).
     */
    public function fractionDigits(): int
    {
        return 0;
    }
}
