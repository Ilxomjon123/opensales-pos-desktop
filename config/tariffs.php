<?php

declare(strict_types=1);

use App\Enums\CommissionType;

return [
    /*
    |--------------------------------------------------------------------------
    | Bepul sinov muddati (kun)
    |--------------------------------------------------------------------------
    | O'zi ro'yxatdan o'tgan dillerga beriladigan bepul kunlar soni.
    */
    'trial_days' => (int) env('DEALER_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Tariflar (sobit narxlar, so'mda)
    |--------------------------------------------------------------------------
    | Registratsiyada diller shu tariflardan birini tanlaydi. Har biri
    | CommissionType ga mos keladi va sobit summa bilan saqlanadi.
    */
    'plans' => [
        CommissionType::FIXED_PER_SHOP->value => 30_000,
        CommissionType::FIXED_PER_ORDER->value => 1_500,
        CommissionType::FIXED_PER_DELIVERYMAN->value => 300_000,
    ],

    /*
    | Davlatga mos tariflar (diller tanlagan davlat valyutasida). Diller country
    | bo'yicha shu summalar olinadi; topilmasa `plans` (UZS) ishlatiladi.
    */
    'plans_by_country' => [
        'uz' => [
            CommissionType::FIXED_PER_SHOP->value => 30_000,
            CommissionType::FIXED_PER_ORDER->value => 1_500,
            CommissionType::FIXED_PER_DELIVERYMAN->value => 300_000,
        ],
        'ru' => [
            CommissionType::FIXED_PER_SHOP->value => 300,
            CommissionType::FIXED_PER_ORDER->value => 15,
            CommissionType::FIXED_PER_DELIVERYMAN->value => 3_000,
        ],
    ],
];
