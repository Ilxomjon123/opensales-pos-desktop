<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RoadDistance extends Model
{
    protected $fillable = [
        'origin_latitude',
        'origin_longitude',
        'dest_latitude',
        'dest_longitude',
        'mode',
        'distance_meters',
        'duration_seconds',
        'fetch_count',
        'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'origin_latitude' => 'float',
            'origin_longitude' => 'float',
            'dest_latitude' => 'float',
            'dest_longitude' => 'float',
            'distance_meters' => 'integer',
            'duration_seconds' => 'integer',
            'fetch_count' => 'integer',
            'last_fetched_at' => 'datetime',
        ];
    }
}
