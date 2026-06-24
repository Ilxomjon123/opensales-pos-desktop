<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case ASSEMBLING = 'assembling';
    case DELIVERING = 'delivering';
    case DELIVERED = 'delivered';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return (string) __('enums.OrderStatus.'.$this->value);
    }

    /**
     * Yangi status oqimi:
     * pending → assembling → delivering → delivered → received
     * pending → cancelled
     * assembling → cancelled
     * (delivering+ ga o'tgach bekor qilib bo'lmaydi)
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING => in_array($next, [self::ASSEMBLING, self::CANCELLED], true),
            self::ASSEMBLING => in_array($next, [self::DELIVERING, self::CANCELLED], true),
            self::DELIVERING => $next === self::DELIVERED,
            self::DELIVERED => $next === self::RECEIVED,
            self::RECEIVED, self::CANCELLED => false,
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::RECEIVED, self::CANCELLED], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::RECEIVED, self::CANCELLED], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING, self::ASSEMBLING], true);
    }
}
