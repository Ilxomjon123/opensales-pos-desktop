<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BroadcastCampaign;
use App\Services\Broadcast\BroadcastCampaignDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Bitta campaign tick'ini ishga tushiradi: audience'ni resolve qiladi va
 * har shop a'zosi uchun SendCampaignMessageJob ni queue ga tashlaydi.
 * ShouldBeUnique — bir vaqtning o'zida bitta campaign uchun ikkita run dispatch bo'lmasin.
 */
final class RunBroadcastCampaignJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $scheduledForIso,
    ) {}

    public function uniqueId(): string
    {
        return "campaign:{$this->campaignId}:{$this->scheduledForIso}";
    }

    public function handle(BroadcastCampaignDispatcher $dispatcher): void
    {
        $campaign = BroadcastCampaign::query()->find($this->campaignId);

        if ($campaign === null || ! $campaign->is_active) {
            return;
        }

        $dispatcher->dispatch($campaign, Carbon::parse($this->scheduledForIso));
    }
}
