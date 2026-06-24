<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'flag',
        'phone_prefix',
        'phone_digits',
        'currency',
        'default_latitude',
        'default_longitude',
        'default_zoom',
        'geo_country_code',
        'bbox',
        'sort',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'phone_digits' => 'integer',
            'currency' => Currency::class,
            'default_latitude' => 'float',
            'default_longitude' => 'float',
            'default_zoom' => 'integer',
            'bbox' => 'array',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Region, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }
}
