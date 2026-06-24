<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DirectoryShop;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DirectoryShopService
{
    /**
     * Koordinatalar bir xil biznes deb hisoblanadigan maksimal farq (gradus).
     * ~0.0015° ≈ 150m — bir nomdagi yaqin filiallarni ajratish uchun.
     */
    private const COORD_EPSILON = 0.0015;

    /**
     * Shop yaratilganda spravochnik bilan moslab, mavjud yozuvga bog'laydi
     * yoki yangi spravochnik yozuvi yaratadi. Shop.directory_id yoziladi.
     */
    public function syncFromShop(Shop $shop, string $source = 'shop_sync'): DirectoryShop
    {
        $directory = $this->findOrCreate([
            'name' => $shop->name,
            'legal_name' => $shop->legal_name,
            'inn' => $shop->inn,
            'phone' => $shop->phone,
            'contact_person' => $shop->contact_person,
            'address' => $shop->address,
            'landmark' => $shop->landmark,
            'region' => $shop->region,
            'district' => $shop->district,
            'latitude' => $shop->latitude !== null ? (float) $shop->latitude : null,
            'longitude' => $shop->longitude !== null ? (float) $shop->longitude : null,
            'photo' => $shop->photo,
        ], $source);

        // Faqat directory_id ustunini yozamiz — `created` observer qayta ishga tushmaydi.
        if ((int) $shop->directory_id !== (int) $directory->id) {
            $shop->forceFill(['directory_id' => $directory->id])->saveQuietly();
        }

        return $directory;
    }

    /**
     * Berilgan atributlar bo'yicha mavjud spravochnik yozuvini topadi (dedup)
     * yoki yangisini yaratadi. CSV import, qo'lda qo'shish va shop-sync uchun.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function findOrCreate(array $attrs, string $source = 'manual'): DirectoryShop
    {
        $match = $this->findMatch(
            inn: $attrs['inn'] ?? null,
            phone: $attrs['phone'] ?? null,
            name: $attrs['name'] ?? null,
            region: $attrs['region'] ?? null,
            district: $attrs['district'] ?? null,
            latitude: isset($attrs['latitude']) ? (float) $attrs['latitude'] : null,
            longitude: isset($attrs['longitude']) ? (float) $attrs['longitude'] : null,
        );

        if ($match !== null) {
            return $match;
        }

        $inn = $this->trimToNull($attrs['inn'] ?? null);

        try {
            // Savepoint ichida — agar bu chaqiruv tashqi tranzaksiya ichida bo'lsa
            // (mas: shop ro'yxati), unique(inn) buzilsa faqat savepoint qaytadi,
            // tashqi tranzaksiya "abort" bo'lmaydi va pastdagi recovery query ishlaydi.
            return DB::transaction(fn (): DirectoryShop => DirectoryShop::query()->create([
                'name' => $attrs['name'] ?? null,
                'legal_name' => $attrs['legal_name'] ?? null,
                'inn' => $inn,
                'phone' => $attrs['phone'] ?? null,
                'phone_normalized' => self::normalizePhone($attrs['phone'] ?? null),
                'contact_person' => $attrs['contact_person'] ?? null,
                'address' => $attrs['address'] ?? null,
                'landmark' => $attrs['landmark'] ?? null,
                'region' => $attrs['region'] ?? null,
                'district' => $attrs['district'] ?? null,
                'latitude' => $attrs['latitude'] ?? null,
                'longitude' => $attrs['longitude'] ?? null,
                'photo' => $attrs['photo'] ?? null,
                'source' => $source,
            ]));
        } catch (UniqueConstraintViolationException $e) {
            // Poyga: boshqa so'rov shu INN bilan yozuvni endigina yaratdi (commit qildi).
            // unique(inn) buzilgani = o'sha yozuv mavjud → uni qaytaramiz, crash bermaymiz.
            if ($inn !== null) {
                $existing = DirectoryShop::query()->forInn($inn)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    /**
     * Dedup prioriteti:
     *   1. INN bor      → faqat INN bo'yicha
     *   2. INN null, tel → telefon (oxirgi 9 raqam) bo'yicha
     *   3. ikkalasi null → nom + viloyat + tuman (+ koordinata yaqinligi)
     */
    public function findMatch(
        ?string $inn,
        ?string $phone,
        ?string $name,
        ?string $region,
        ?string $district,
        ?float $latitude,
        ?float $longitude,
    ): ?DirectoryShop {
        $inn = $this->trimToNull($inn);
        if ($inn !== null) {
            return DirectoryShop::query()->forInn($inn)->first();
        }

        $tail = self::normalizePhone($phone);
        if ($tail !== null) {
            return DirectoryShop::query()->forPhoneTail($tail)->first();
        }

        $name = $this->trimToNull($name);
        if ($name === null) {
            return null;
        }

        $candidates = DirectoryShop::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where('region', $region)
            ->where('district', $district)
            ->get();

        return $this->closestWithinEpsilon($candidates, $latitude, $longitude);
    }

    /**
     * Koordinata bo'lsa — eng yaqin (epsilon ichidagi) nomzodni tanlaydi.
     * Koordinata bo'lmasa — birinchi nomzod (nom+hudud mos).
     *
     * @param  Collection<int, DirectoryShop>  $candidates
     */
    private function closestWithinEpsilon(Collection $candidates, ?float $lat, ?float $lng): ?DirectoryShop
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($lat === null || $lng === null) {
            return $candidates->first();
        }

        $best = null;
        $bestDist = null;

        foreach ($candidates as $candidate) {
            if ($candidate->latitude === null || $candidate->longitude === null) {
                continue;
            }

            $dLat = $candidate->latitude - $lat;
            $dLng = $candidate->longitude - $lng;
            $dist = ($dLat * $dLat) + ($dLng * $dLng);

            if ($dist <= self::COORD_EPSILON * self::COORD_EPSILON && ($bestDist === null || $dist < $bestDist)) {
                $best = $candidate;
                $bestDist = $dist;
            }
        }

        // Epsilon ichida koordinatali mos topilmasa — koordinatasiz nomzodga (nom+hudud mos) qaytamiz.
        return $best ?? $candidates->firstWhere(fn (DirectoryShop $c): bool => $c->latitude === null);
    }

    /**
     * Telefonni faqat raqamga keltirib oxirgi 9 tasini qaytaradi (yo'q bo'lsa null).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 7 ? substr($digits, -9) : null;
    }

    private function trimToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
