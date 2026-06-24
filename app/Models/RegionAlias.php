<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class RegionAlias extends Model
{
    protected $fillable = [
        'aliasable_type',
        'aliasable_id',
        'alias',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function aliasable(): MorphTo
    {
        return $this->morphTo();
    }
}
