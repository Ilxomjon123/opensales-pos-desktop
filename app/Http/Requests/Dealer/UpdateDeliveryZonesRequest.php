<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Models\Country;
use App\Services\Geo\GeoCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateDeliveryZonesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDealer() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zones' => ['present', 'array'],
            'zones.*.region' => ['required', 'string', 'max:100'],
            'zones.*.whole_region' => ['required', 'boolean'],
            'zones.*.districts' => ['array'],
            'zones.*.districts.*' => ['string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $dealer = $this->user()?->dealer;
            $country = $dealer?->country
                ?? Country::query()->where('code', 'uz')->first();

            if ($country === null) {
                return;
            }

            $regionMap = [];

            foreach (app(GeoCatalog::class)->regionOptions($country) as $region) {
                $regionMap[$region['name']] = $region['districts'];
            }

            foreach ((array) $this->input('zones', []) as $i => $zone) {
                $regionName = $zone['region'] ?? null;

                if ($regionName === null || ! isset($regionMap[$regionName])) {
                    $v->errors()->add("zones.{$i}.region", "Noma'lum viloyat: {$regionName}.");

                    continue;
                }

                $wholeRegion = filter_var($zone['whole_region'] ?? false, FILTER_VALIDATE_BOOL);

                if ($wholeRegion) {
                    continue;
                }

                $valid = $regionMap[$regionName];

                foreach ((array) ($zone['districts'] ?? []) as $j => $district) {
                    if (! in_array($district, $valid, true)) {
                        $v->errors()->add(
                            "zones.{$i}.districts.{$j}",
                            "Noma'lum tuman: {$district} ({$regionName})."
                        );
                    }
                }
            }
        });
    }
}
