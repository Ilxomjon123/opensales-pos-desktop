<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastMessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BroadcastMessage extends Model
{
    use Prunable;

    private const RETENTION_DAYS = 60;

    protected $fillable = [
        'run_id',
        'shop_id',
        'dealer_id',
        'chat_id',
        'status',
        'telegram_message_id',
        'error',
        'sent_at',
    ];

    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }

    protected function casts(): array
    {
        return [
            'status' => BroadcastMessageStatus::class,
            'chat_id' => 'integer',
            'telegram_message_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(BroadcastRun::class, 'run_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }
}
