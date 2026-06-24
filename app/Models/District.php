<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DistrictFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class District extends Model
{
    /** @use HasFactory<DistrictFactory> */
    use HasFactory;

    protected $fillable = [
        'region_id',
        'name',
        'sort',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'region_id' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return MorphMany<RegionAlias, $this>
     */
    public function aliases(): MorphMany
    {
        return $this->morphMany(RegionAlias::class, 'aliasable');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
