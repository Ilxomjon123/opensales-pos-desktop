<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case DEALER = 'dealer';
    case WAREHOUSE = 'warehouse';
    case DELIVERYMAN = 'deliveryman';
    case CASHIER = 'cashier';

    public function label(): string
    {
        return (string) __('enums.UserRole.'.$this->value);
    }

    /**
     * Diller tashkilotiga tegishli barcha rollar (super_admin'siz).
     *
     * @return list<self>
     */
    public static function dealerStaff(): array
    {
        return [self::DEALER, self::WAREHOUSE, self::DELIVERYMAN, self::CASHIER];
    }
}
