<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DirectoryShopFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DirectoryShop extends Model
{
    /** @use HasFactory<DirectoryShopFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'inn',
        'phone',
        'phone_normalized',
        'contact_person',
        'address',
        'landmark',
        'region',
        'district',
        'country_id',
        'region_id',
        'district_id',
        'latitude',
        'longitude',
        'photo',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'directory_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function regionRef(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function districtRef(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    /**
     * Faollashgan ulanishlar — kamida bitta bot a'zosi biriktirilgan shoplar.
     */
    public function activatedShops(): HasMany
    {
        return $this->shops()->whereHas('members');
    }

    public function scopeForInn(Builder $query, string $inn): void
    {
        $query->where('inn', $inn);
    }

    public function scopeForPhoneTail(Builder $query, string $tail): void
    {
        $query->where('phone_normalized', $tail);
    }
}
