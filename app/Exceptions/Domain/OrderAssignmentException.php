<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class OrderAssignmentException extends DomainException
{
    public static function alreadyAssigned(): self
    {
        return new self('Bu buyurtma allaqachon yetkazib beruvchiga biriktirilgan');
    }

    public static function lockedAtStatus(string $statusLabel): self
    {
        return new self("Yetkazib beruvchini '{$statusLabel}' bosqichida o'zgartirib bo'lmaydi");
    }

    public static function notADeliveryman(): self
    {
        return new self('Faqat yetkazib beruvchi rolidagi xodim biriktirilishi mumkin');
    }

    public static function deliverymanNotFound(): self
    {
        return new self('Yetkazib beruvchi topilmadi yoki boshqa diller xodimi');
    }

    public static function notAssignedToYou(): self
    {
        return new self('Bu buyurtma sizga biriktirilmagan');
    }

    public static function notInAssignableStatus(string $statusLabel): self
    {
        return new self("Buyurtma '{$statusLabel}' bosqichida — o'ziga olib bo'lmaydi");
    }
}
