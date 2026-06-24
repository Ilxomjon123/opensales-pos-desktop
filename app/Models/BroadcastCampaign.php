<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastMediaType;
use App\Enums\BroadcastScheduleType;
use Database\Factories\BroadcastCampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BroadcastCampaign extends Model
{
    /** @use HasFactory<BroadcastCampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'dealer_id',
        'created_by_user_id',
        'title',
        'message_text',
        'media_path',
        'media_type',
        'telegram_file_id',
        'buttons',
        'audience_type',
        'audience_config',
        'schedule_type',
        'schedule_config',
        'timezone',
        'starts_at',
        'ends_at',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'buttons' => 'array',
            'audience_config' => 'array',
            'schedule_config' => 'array',
            'audience_type' => BroadcastAudienceType::class,
            'schedule_type' => BroadcastScheduleType::class,
            'media_type' => BroadcastMediaType::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(BroadcastRun::class, 'campaign_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeDueAt(Builder $query, \DateTimeInterface $moment): void
    {
        $query->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $moment)
            ->where(function (Builder $q) use ($moment): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $moment);
            });
    }

    public function scopeForDealer(Builder $query, int $dealerId): void
    {
        $query->where('dealer_id', $dealerId);
    }

    public function isPlatformLevel(): bool
    {
        return $this->dealer_id === null;
    }
}
