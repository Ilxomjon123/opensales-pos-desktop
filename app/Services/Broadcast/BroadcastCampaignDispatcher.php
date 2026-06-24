<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Enums\BroadcastMessageStatus;
use App\Enums\BroadcastRunStatus;
use App\Jobs\SendCampaignMessageJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\BroadcastRun;
use App\Models\ShopMember;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Campaign ni Run ga aylantiradi, har shop a'zosi uchun queue ga job tashlaydi.
 * Rate-limit: SendCampaignMessageJob da Redis throttle middleware (`telegram-bot`).
 *
 * Tartib:
 *   1. Audience LazyCollection ko'rinishida streaming (RAM-safe)
 *   2. DB transaction ichida: Run + BroadcastMessage rows (chunked batch insert)
 *   3. Transaction tugagandan keyin SendCampaignMessageJob staggered delay bilan queue ga
 *      (afterCommit, worker DB ga yetib bormagan rowga urinishidan saqlanadi).
 */
final class BroadcastCampaignDispatcher
{
    private const INSERT_CHUNK = 500;

    private const STAGGER_MS = 50;

    public function __construct(
        private readonly BroadcastAudienceResolver $audience,
        private readonly BroadcastRenderer $renderer,
        private readonly NotificationService $notifications,
    ) {}

    public function dispatch(BroadcastCampaign $campaign, ?Carbon $scheduledFor = null): BroadcastRun
    {
        $scheduledFor ??= Carbon::now();
        $now = Carbon::now();

        $run = DB::transaction(function () use ($campaign, $scheduledFor, $now): BroadcastRun {
            $run = BroadcastRun::create([
                'campaign_id' => $campaign->id,
                'scheduled_for' => $scheduledFor,
                'started_at' => $now,
                'total_recipients' => 0,
                'status' => BroadcastRunStatus::RUNNING,
            ]);

            $insertedAt = $now->toDateTimeString();
            $queued = BroadcastMessageStatus::QUEUED->value;
            $inserted = 0;

            $this->audience
                ->resolve($campaign)
                ->chunk(self::INSERT_CHUNK)
                ->each(function ($chunk) use ($run, $insertedAt, $queued, &$inserted): void {
                    $rows = $chunk->map(fn (array $r): array => [
                        'run_id' => $run->id,
                        'shop_id' => $r['shop_id'],
                        'dealer_id' => $r['dealer_id'],
                        'chat_id' => $r['chat_id'],
                        'status' => $queued,
                        'created_at' => $insertedAt,
                        'updated_at' => $insertedAt,
                    ])->all();

                    BroadcastMessage::insert($rows);
                    $inserted += count($rows);
                });

            $run->forceFill(['total_recipients' => $inserted])->save();

            // Scheduling (last_run_at / next_run_at) DispatchDueBroadcastsCommand'da
            // claim paytida atomik bajariladi — bu yerda EMAS. Aks holda async job
            // kechiksa, kampaniya due-oynada qolib har daqiqa qayta yuborilardi.

            return $run;
        });

        // Mobil ilova mijozlariga (feed + FCM) — Telegram qabul qiluvchilardan mustaqil.
        $this->notifyMobile($campaign);

        if ($run->total_recipients === 0) {
            $run->forceFill([
                'status' => BroadcastRunStatus::COMPLETED,
                'completed_at' => Carbon::now(),
            ])->save();

            return $run;
        }

        $this->queueMessageJobs($run->id, $now);

        return $run;
    }

    private function notifyMobile(BroadcastCampaign $campaign): void
    {
        $campaign->loadMissing('dealer');
        $dealer = $campaign->dealer;
        $title = $campaign->title !== '' ? $campaign->title : ($dealer?->name ?? config('app.name', 'OpenSales'));

        $this->notifications->broadcast(
            $title,
            $this->audience->mobileMembers($campaign),
            fn (ShopMember $m): string => $this->renderer->render($campaign, $m->shop, $m->shop?->dealer ?? $dealer, $m),
        );
    }

    /**
     * Transaction commitidan keyin chaqiriladi.
     * Insert qilingan BroadcastMessage rowlarini ID + dealer_id bo'yicha topadi,
     * SendCampaignMessageJob ni staggered delay bilan queue ga tashlaydi.
     */
    private function queueMessageJobs(int $runId, Carbon $now): void
    {
        $idx = 0;

        BroadcastMessage::query()
            ->where('run_id', $runId)
            ->orderBy('id')
            ->select(['id', 'dealer_id'])
            ->chunkById(self::INSERT_CHUNK, function ($messages) use ($now, &$idx): void {
                foreach ($messages as $m) {
                    SendCampaignMessageJob::dispatch(
                        messageId: (int) $m->id,
                        dealerId: (int) $m->dealer_id,
                    )->delay($now->copy()->addMilliseconds(self::STAGGER_MS * $idx));

                    $idx++;
                }
            });
    }
}
