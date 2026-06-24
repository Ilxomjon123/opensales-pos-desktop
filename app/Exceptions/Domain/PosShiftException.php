<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class PosShiftException extends DomainException
{
    public static function alreadyOpen(): self
    {
        return new self('Sizda allaqachon ochiq smena bor — avval uni yoping.');
    }

    public static function noOpenShift(): self
    {
        return new self('Sotuv qilish uchun avval smenani oching.');
    }

    public static function notOpen(): self
    {
        return new self('Bu smena ochiq emas.');
    }

    public static function notYourShift(): self
    {
        return new self('Bu smena boshqa kassirga tegishli.');
    }

    public static function shiftHasOpenSales(): self
    {
        return new self('Smenada qarzga sotuv bor — avval ularni hal qiling yoki yopishni tasdiqlang.');
    }
}
