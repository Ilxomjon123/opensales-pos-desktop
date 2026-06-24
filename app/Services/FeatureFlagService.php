<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FeatureFlag;
use Laravel\Pennant\Feature;

/**
 * Davlat bo'yicha funksiya bayroqlarini (Pennant) o'qish/yozish. Scope —
 * davlat kodi (`countries.code`). Bayroqlar AppServiceProvider'da
 * `Feature::define` orqali ro'yxatdan o'tkaziladi (standart qiymat bilan).
 */
final class FeatureFlagService
{
    /**
     * Bitta davlat uchun barcha bayroqlar holati. Kalit — bayroq qiymati
     * (`phone-login`), qiymat — yoqilganmi.
     *
     * @return array<string, bool>
     */
    public function forCountry(string $countryCode): array
    {
        $result = [];

        foreach (FeatureFlag::manageable() as $flag) {
            $result[$flag->value] = Feature::for($countryCode)->active($flag->value);
        }

        return $result;
    }

    /**
     * Mobil ilova uchun config — kalitlar camelCase (`phoneLoginEnabled`).
     *
     * @return array<string, bool>
     */
    public function mobileConfig(string $countryCode): array
    {
        $result = [];

        foreach (FeatureFlag::manageable() as $flag) {
            $result[$flag->mobileKey()] = Feature::for($countryCode)->active($flag->value);
        }

        return $result;
    }

    /**
     * Admin UI uchun matritsa: davlat kodi => {bayroq qiymati: bool}.
     *
     * @param  iterable<string>  $countryCodes
     * @return array<string, array<string, bool>>
     */
    public function matrix(iterable $countryCodes): array
    {
        $matrix = [];

        foreach ($countryCodes as $code) {
            $matrix[$code] = $this->forCountry($code);
        }

        return $matrix;
    }

    /**
     * Bitta davlat uchun bitta bayroqni yoqish/o'chirish.
     */
    public function setForCountry(string $countryCode, FeatureFlag $flag, bool $enabled): void
    {
        if ($enabled) {
            Feature::for($countryCode)->activate($flag->value);
        } else {
            Feature::for($countryCode)->deactivate($flag->value);
        }
    }
}
