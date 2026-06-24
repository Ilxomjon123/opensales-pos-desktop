<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Davlat bo'yicha boshqariladigan funksiya bayroqlari (Laravel Pennant).
 * Scope — davlat kodi (`countries.code`, masalan 'uz', 'ru'). Super admin
 * har davlat uchun alohida yoqadi/o'chiradi. Mobil ilova joriy davlatига
 * mos qiymatlarni `/api/mobile/config` orqali oladi.
 */
enum FeatureFlag: string
{
    case PHONE_LOGIN = 'phone-login';
    case TELEGRAM_LOGIN = 'telegram-login';
    case QR_LOGIN = 'qr-login';

    /**
     * Boshqariladigan barcha bayroqlar (admin UI va mobil config tartibi shu).
     *
     * @return list<self>
     */
    public static function manageable(): array
    {
        return self::cases();
    }

    /**
     * Pennant'da hali qiymat saqlanmagan (davlat) uchun standart holat.
     */
    public function defaultEnabled(): bool
    {
        return true;
    }

    /**
     * Mobil config JSON'idagi kalit (camelCase).
     */
    public function mobileKey(): string
    {
        return match ($this) {
            self::PHONE_LOGIN => 'phoneLoginEnabled',
            self::TELEGRAM_LOGIN => 'telegramLoginEnabled',
            self::QR_LOGIN => 'qrLoginEnabled',
        };
    }
}
