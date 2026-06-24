<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
final class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'flag' => $this->flag,
            'phone_prefix' => $this->phone_prefix,
            'phone_digits' => $this->phone_digits,
            'currency' => $this->currency->value,
            'currency_symbol' => $this->currency->symbol(),
            'default_center' => $this->default_latitude !== null && $this->default_longitude !== null
                ? ['lat' => $this->default_latitude, 'lng' => $this->default_longitude, 'zoom' => $this->default_zoom]
                : null,
        ];
    }
}
