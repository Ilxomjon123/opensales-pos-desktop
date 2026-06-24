<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class RoutingException extends DomainException
{
    public static function apiKeyMissing(): self
    {
        return new self('Yandex Routing API kaliti sozlanmagan. .env faylida YANDEX_ROUTING_API_KEY ko\'rsating.');
    }

    public static function apiError(string $message): self
    {
        return new self("Yo'l masofasini hisoblashda xatolik: {$message}");
    }

    public static function warehouseNotConfigured(): self
    {
        $exception = new self('Diller ombori koordinatasi sozlanmagan. Sozlamalardan kiriting.');
        $exception->errorCode = 'warehouse_not_configured';

        return $exception;
    }

    public static function tooManyPoints(int $count, int $max): self
    {
        return new self("Buyurtmalar soni juda ko'p: {$count}. Maksimal {$max} tagacha optimizatsiya qilinadi.");
    }

    public static function emptyRoute(): self
    {
        return new self('Marshrut tuzish uchun buyurtma yo\'q.');
    }
}
