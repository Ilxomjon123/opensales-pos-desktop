<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DealerDeliveryZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DealerDeliveryZone extends Model
{
    /** @use HasFactory<DealerDeliveryZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'dealer_id',
        'region',
        'district',
        'country_id',
        'region_id',
        'district_id',
    ];

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
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

    public function scopeForDealer(Builder $query, int $dealerId): void
    {
        $query->where('dealer_id', $dealerId);
    }
}
