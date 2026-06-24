<?php

declare(strict_types=1);

namespace App\Services\Routing;

/**
 * Marshrutdagi bir nuqta — buyurtmaga (yoki omborga) referensiya saqlaydi.
 *
 * `payload` — buyurtma ma'lumotlari (id, shop info), routing logikasi
 * uchun shaffof. RouteOptimizer payloadni o'qimaydi, faqat coord ishlatadi.
 *
 * @template T
 */
final readonly class RouteStop
{
    /**
     * @param  T  $payload
     */
    public function __construct(
        public Coordinate $coordinate,
        public mixed $payload,
    ) {}
}
