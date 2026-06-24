<?php

declare(strict_types=1);

namespace App\Contracts;

interface ForwardGeocoderInterface
{
    /**
     * Viloyat va (ixtiyoriy) tuman nomidan markaziy koordinatani topadi.
     * Topilmasa null qaytadi. Zoom — Leaflet zoom darajasi (district uchun
     * yaqinroq, region uchun uzoqroq).
     *
     * @return array{lat: float, lng: float, zoom: int}|null
     */
    public function forward(string $region, ?string $district = null): ?array;
}
