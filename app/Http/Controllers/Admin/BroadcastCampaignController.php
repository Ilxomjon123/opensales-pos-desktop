<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastMediaType;
use App\Enums\BroadcastScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBroadcastCampaignRequest;
use App\Http\Requests\Admin\UpdateBroadcastCampaignRequest;
use App\Jobs\RunBroadcastCampaignJob;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Services\Broadcast\BroadcastAudienceResolver;
use App\Services\Broadcast\BroadcastSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class BroadcastCampaignController extends Controller
{
    private const DISK = 'public';

    public function __construct(
        private readonly BroadcastSchedulerService $scheduler,
        private readonly BroadcastAudienceResolver $audience,
    ) {}

    public function index(Request $request): Response
    {
        $campaigns = BroadcastCampaign::query()
            ->whereNull('dealer_id')
            ->withCount(['runs'])
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (BroadcastCampaign $c): array => $this->serialize($c));

        return Inertia::render('Admin/BroadcastCampaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/BroadcastCampaigns/Edit', [
            'campaign' => null,
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreBroadcastCampaignRequest $request): RedirectResponse
    {
        $campaign = new BroadcastCampaign($this->payload($request));
        $campaign->dealer_id = null;
        $campaign->created_by_user_id = (int) $request->user()->id;

        if ($request->hasFile('media')) {
            $campaign->media_path = $this->storeMedia($request->file('media'));
        }

        $campaign->save();
        $this->refreshNextRun($campaign);

        return redirect()
            ->route('admin.broadcast-campaigns.index')
            ->with('status', "Platform kampaniyasi yaratildi: {$campaign->title}");
    }

    public function show(BroadcastCampaign $campaign): Response
    {
        abort_unless($campaign->dealer_id === null, 404);
        $campaign->load(['runs' => fn ($q) => $q->latest('scheduled_for')->limit(20)]);

        return Inertia::render('Admin/BroadcastCampaigns/Show', [
            'campaign' => $this->serialize($campaign, withRuns: true),
        ]);
    }

    public function edit(BroadcastCampaign $campaign): Response
    {
        abort_unless($campaign->dealer_id === null, 404);

        return Inertia::render('Admin/BroadcastCampaigns/Edit', [
            'campaign' => $this->serialize($campaign),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateBroadcastCampaignRequest $request, BroadcastCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->dealer_id === null, 404);

        $campaign->fill($this->payload($request));

        if ($request->boolean('remove_media') && $campaign->media_path !== null) {
            Storage::disk(self::DISK)->delete($campaign->media_path);
            $campaign->media_path = null;
            $campaign->media_type = null;
        }

        if ($request->hasFile('media')) {
            if ($campaign->media_path !== null) {
                Storage::disk(self::DISK)->delete($campaign->media_path);
            }

            $campaign->media_path = $this->storeMedia($request->file('media'));
        }

        $campaign->save();
        $this->refreshNextRun($campaign);

        return redirect()
            ->route('admin.broadcast-campaigns.index')
            ->with('status', "Kampaniya yangilandi: {$campaign->title}");
    }

    public function toggle(BroadcastCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->dealer_id === null, 404);

        $campaign->is_active = ! $campaign->is_active;
        $campaign->save();

        $this->refreshNextRun($campaign);

        $msg = $campaign->is_active ? 'Kampaniya yoqildi' : 'Kampaniya pauza qilindi';

        return back()->with('status', $msg);
    }

    public function destroy(BroadcastCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->dealer_id === null, 404);

        if ($campaign->media_path !== null) {
            Storage::disk(self::DISK)->delete($campaign->media_path);
        }

        $campaign->delete();

        return redirect()
            ->route('admin.broadcast-campaigns.index')
            ->with('status', 'Kampaniya o\'chirildi');
    }

    public function runNow(BroadcastCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->dealer_id === null, 404);

        RunBroadcastCampaignJob::dispatch(
            campaignId: $campaign->id,
            scheduledForIso: Carbon::now()->toIso8601String(),
        );

        return back()->with('status', 'Kampaniya darhol yuborishga qo\'yildi');
    }

    public function preview(Request $request): JsonResponse
    {
        $temp = new BroadcastCampaign([
            'audience_type' => $request->string('audience_type')->toString(),
            'audience_config' => (array) $request->input('audience_config', []),
        ]);
        $temp->dealer_id = $request->integer('dealer_id') ?: null;

        return response()->json([
            'count' => $this->audience->count($temp),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $data = $request->validated();

        return [
            'title' => $data['title'],
            'message_text' => $data['message_text'],
            'media_type' => $request->hasFile('media')
                ? ($data['media_type'] ?? BroadcastMediaType::PHOTO->value)
                : ($data['media_type'] ?? null),
            'buttons' => $this->normalizeButtons((array) ($data['buttons'] ?? [])),
            'audience_type' => $data['audience_type'],
            'audience_config' => (array) ($data['audience_config'] ?? []) ?: null,
            'schedule_type' => $data['schedule_type'],
            'schedule_config' => $this->normalizeSchedule(
                BroadcastScheduleType::from($data['schedule_type']),
                (array) ($data['schedule_config'] ?? []),
            ),
            'timezone' => $data['timezone'] ?? 'Asia/Tashkent',
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<int, array{text:string,url:string}>>|null
     */
    private function normalizeButtons(array $rows): ?array
    {
        $out = [];

        foreach ($rows as $row) {
            $rowOut = [];

            foreach ((array) $row as $btn) {
                $text = trim((string) ($btn['text'] ?? ''));
                $url = trim((string) ($btn['url'] ?? ''));

                if ($text === '' || $url === '') {
                    continue;
                }

                $rowOut[] = ['text' => $text, 'url' => $url];
            }

            if ($rowOut !== []) {
                $out[] = $rowOut;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeSchedule(BroadcastScheduleType $type, array $config): array
    {
        return match ($type) {
            BroadcastScheduleType::ONCE => ['datetime' => (string) ($config['datetime'] ?? '')],
            BroadcastScheduleType::DAILY => ['times' => $this->normalizeTimes($config)],
            BroadcastScheduleType::WEEKLY,
            BroadcastScheduleType::MONTHLY => [
                'times' => $this->normalizeTimes($config),
                'days' => array_map('intval', (array) ($config['days'] ?? [])),
            ],
        };
    }

    /**
     * Bir kunda bir necha yuborish vaqti. `times[]` formatini, eski yagona
     * `time` formatini ham qabul qiladi. Natija HH:MM, dedup, sortlangan.
     *
     * @param  array<string,mixed>  $config
     * @return list<string>
     */
    private function normalizeTimes(array $config): array
    {
        $raw = $config['times'] ?? null;

        if (! is_array($raw) || $raw === []) {
            $raw = [$config['time'] ?? '09:00'];
        }

        $times = [];

        foreach ($raw as $value) {
            if (preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) $value), $m) !== 1) {
                continue;
            }

            $hm = sprintf('%02d:%02d', min(23, (int) $m[1]), min(59, (int) $m[2]));
            $times[$hm] = $hm;
        }

        if ($times === []) {
            $times['09:00'] = '09:00';
        }

        ksort($times);

        return array_values($times);
    }

    private function refreshNextRun(BroadcastCampaign $campaign): void
    {
        $campaign->next_run_at = $campaign->is_active
            ? $this->scheduler->nextRunAt($campaign)
            : null;

        $campaign->save();
    }

    private function storeMedia(UploadedFile $file): string
    {
        return $file->store('platform/broadcasts', self::DISK);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'audience_types' => [
                ['value' => BroadcastAudienceType::PLATFORM_DEALERS->value, 'label' => BroadcastAudienceType::PLATFORM_DEALERS->label()],
                ['value' => BroadcastAudienceType::PLATFORM_SHOP_MEMBERS->value, 'label' => BroadcastAudienceType::PLATFORM_SHOP_MEMBERS->label()],
            ],
            'schedule_types' => array_map(
                fn (BroadcastScheduleType $t): array => ['value' => $t->value, 'label' => $t->label()],
                BroadcastScheduleType::cases(),
            ),
            'media_types' => array_map(
                fn (BroadcastMediaType $t): array => ['value' => $t->value, 'label' => $t->label()],
                BroadcastMediaType::cases(),
            ),
            'dealers' => Dealer::query()->active()->orderBy('name')->get(['id', 'name'])->toArray(),
            'placeholders' => ['{dealer_name}', '{date}', '{time}'],
            'timezone' => 'Asia/Tashkent',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BroadcastCampaign $campaign, bool $withRuns = false): array
    {
        $data = [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'message_text' => $campaign->message_text,
            'media_path' => $campaign->media_path,
            'media_url' => $campaign->media_path !== null ? Storage::disk(self::DISK)->url($campaign->media_path) : null,
            'media_type' => $campaign->media_type?->value,
            'buttons' => $campaign->buttons,
            'audience_type' => $campaign->audience_type?->value,
            'audience_config' => $campaign->audience_config,
            'schedule_type' => $campaign->schedule_type?->value,
            'schedule_config' => $campaign->schedule_config,
            'timezone' => $campaign->timezone,
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'is_active' => $campaign->is_active,
            'last_run_at' => $campaign->last_run_at?->toIso8601String(),
            'next_run_at' => $campaign->next_run_at?->toIso8601String(),
            'runs_count' => $campaign->runs_count ?? null,
        ];

        if ($withRuns && $campaign->relationLoaded('runs')) {
            $data['runs'] = $campaign->runs->map(fn ($r): array => [
                'id' => $r->id,
                'scheduled_for' => $r->scheduled_for?->toIso8601String(),
                'started_at' => $r->started_at?->toIso8601String(),
                'completed_at' => $r->completed_at?->toIso8601String(),
                'total_recipients' => $r->total_recipients,
                'success_count' => $r->success_count,
                'failed_count' => $r->failed_count,
                'status' => $r->status?->value,
            ])->all();
        }

        return $data;
    }
}
