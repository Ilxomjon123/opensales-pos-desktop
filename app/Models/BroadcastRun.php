<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BroadcastRun extends Model
{
    protected $fillable = [
        'campaign_id',
        'scheduled_for',
        'started_at',
        'completed_at',
        'total_recipients',
        'success_count',
        'failed_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => BroadcastRunStatus::class,
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_recipients' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BroadcastCampaign::class, 'campaign_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BroadcastMessage::class, 'run_id');
    }
}
