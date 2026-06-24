<?php

declare(strict_types=1);

namespace App\Contracts;

interface InnLookupServiceInterface
{
    /**
     * INN/STIR bo'yicha tashkilot ma'lumotlarini qidiradi.
     *
     * @return array{
     *     inn: string,
     *     name: string|null,
     *     legal_name: string|null,
     *     region: string|null,
     *     district: string|null,
     *     address: string|null
     * }|null Ma'lumot topilmasa yoki xizmat javob bermasa — null.
     */
    public function lookup(string $inn): ?array;
}
