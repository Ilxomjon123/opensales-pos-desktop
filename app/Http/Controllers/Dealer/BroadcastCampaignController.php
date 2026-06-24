<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastMediaType;
use App\Enums\BroadcastScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreBroadcastCampaignRequest;
use App\Http\Requests\Dealer\UpdateBroadcastCampaignRequest;
use App\Jobs\RunBroadcastCampaignJob;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\Broadcast\BroadcastAudienceResolver;
use App\Services\Broadcast\BroadcastRenderer;
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
        private readonly BroadcastRenderer $renderer,
    ) {}

    public function index(Request $request): Response
    {
        $dealer = $this->dealer($request);

        $campaigns = BroadcastCampaign::query()
            ->forDealer($dealer->id)
            ->withCount(['runs'])
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (BroadcastCampaign $c): array => $this->serialize($c));

        return Inertia::render('Dealer/BroadcastCampaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Dealer/BroadcastCampaigns/Edit', [
            'campaign' => null,
            'options' => $this->formOptions($this->dealer($request)),
        ]);
    }

    public function store(StoreBroadcastCampaignRequest $request): RedirectResponse
    {
        $dealer = $this->dealer($request);

        $campaign = new BroadcastCampaign($this->payload($request, $dealer));
        $campaign->dealer_id = $dealer->id;
        $campaign->created_by_user_id = (int) $request->user()->id;

        if ($request->hasFile('media')) {
            $campaign->media_path = $this->storeMedia($request->file('media'), $dealer->id);
        }

        $campaign->save();
        $this->refreshNextRun($campaign);

        return redirect()
            ->route('dealer.broadcast-campaigns.index')
            ->with('status', "Kampaniya yaratildi: {$campaign->title}");
    }

    public function show(Request $request, BroadcastCampaign $campaign): Response
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->load(['runs' => fn ($q) => $q->latest('scheduled_for')->limit(20)]);

        return Inertia::render('Dealer/BroadcastCampaigns/Show', [
            'campaign' => $this->serialize($campaign, withRuns: true),
        ]);
    }

    public function edit(Request $request, BroadcastCampaign $campaign): Response
    {
        $this->authorizeCampaign($request, $campaign);

        return Inertia::render('Dealer/BroadcastCampaigns/Edit', [
            'campaign' => $this->serialize($campaign),
            'options' => $this->formOptions($this->dealer($request)),
        ]);
    }

    public function update(UpdateBroadcastCampaignRequest $request, BroadcastCampaign $campaign): RedirectResponse
    {
        $dealer = $this->dealer($request);

        $campaign->fill($this->payload($request, $dealer));

        if ($request->boolean('remove_media') && $campaign->media_path !== null) {
            Storage::disk(self::DISK)->delete($campaign->media_path);
            $campaign->media_path = null;
            $campaign->media_type = null;
        }

        if ($request->hasFile('media')) {
            if ($campaign->media_path !== null) {
                Storage::disk(self::DISK)->delete($campaign->media_path);
            }

            $campaign->media_path = $this->storeMedia($request->file('media'), $dealer->id);
        }

        $campaign->save();
        $this->refreshNextRun($campaign);

        return redirect()
            ->route('dealer.broadcast-campaigns.index')
            ->with('status', "Kampaniya yangilandi: {$campaign->title}");
    }

    public function toggle(Request $request, BroadcastCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->is_active = ! $campaign->is_active;
        $campaign->save();

        $this->refreshNextRun($campaign);

        $msg = $campaign->is_active ? 'Kampaniya yoqildi' : 'Kampaniya pauza qilindi';

        return back()->with('status', $msg);
    }

    public function destroy(Request $request, BroadcastCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        if ($campaign->media_path !== null) {
            Storage::disk(self::DISK)->delete($campaign->media_path);
        }

        $campaign->delete();

        return redirect()
            ->route('dealer.broadcast-campaigns.index')
            ->with('status', 'Kampaniya o\'chirildi');
    }

    public function runNow(Request $request, BroadcastCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        RunBroadcastCampaignJob::dispatch(
            campaignId: $campaign->id,
            scheduledForIso: Carbon::now()->toIso8601String(),
        );

        return back()->with('status', 'Kampaniya darhol yuborishga qo\'yildi');
    }

    public function preview(Request $request): JsonResponse
    {
        $dealer = $this->dealer($request);

        $temp = new BroadcastCampaign([
            'audience_type' => $request->string('audience_type')->toString(),
            'audience_config' => (array) $request->input('audience_config', []),
        ]);
        $temp->dealer_id = $dealer->id;

        return response()->json([
            'count' => $this->audience->count($temp),
        ]);
    }

    public function renderPreview(Request $request, BroadcastCampaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $shop = Shop::query()->forDealer($campaign->dealer_id ?? 0)->first();
        $member = $shop?->members()->first();

        return response()->json([
            'text' => $this->renderer->render($campaign, $shop, $campaign->dealer, $member),
        ]);
    }

    private function dealer(Request $request): Dealer
    {
        return Dealer::query()->findOrFail((int) $request->user()->dealer_id);
    }

    private function authorizeCampaign(Request $request, BroadcastCampaign $campaign): void
    {
        abort_unless((int) $campaign->dealer_id === (int) $request->user()->dealer_id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, Dealer $dealer): array
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
            'audience_config' => $this->normalizeAudience(
                BroadcastAudienceType::from($data['audience_type']),
                (array) ($data['audience_config'] ?? []),
                $dealer,
            ),
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
     * @return array<string, mixed>|null
     */
    private function normalizeAudience(BroadcastAudienceType $type, array $config, Dealer $dealer): ?array
    {
        if ($type === BroadcastAudienceType::SELECTED_SHOPS) {
            $ids = array_map('intval', (array) ($config['shop_ids'] ?? []));
            $validIds = Shop::query()
                ->forDealer($dealer->id)
                ->whereIn('id', $ids === [] ? [0] : $ids)
                ->pluck('id')
                ->all();

            return ['shop_ids' => $validIds];
        }

        if ($type === BroadcastAudienceType::FILTER) {
            return array_filter([
                'balance_op' => $config['balance_op'] ?? null,
                'balance_value' => isset($config['balance_value']) ? (int) $config['balance_value'] : null,
                'debtors_only' => isset($config['debtors_only']) ? (bool) $config['debtors_only'] : null,
                'min_days_since_last_order' => isset($config['min_days_since_last_order'])
                    ? (int) $config['min_days_since_last_order'] : null,
                'region' => $config['region'] ?? null,
                'category_ids' => isset($config['category_ids']) ? array_map('intval', (array) $config['category_ids']) : null,
            ], fn ($v): bool => $v !== null && $v !== '');
        }

        return null;
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

    private function storeMedia(UploadedFile $file, int $dealerId): string
    {
        return $file->store("dealers/{$dealerId}/broadcasts", self::DISK);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Dealer $dealer): array
    {
        return [
            'shops' => Shop::query()
                ->forDealer($dealer->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'region', 'balance'])
                ->toArray(),
            'categories' => ProductCategory::query()
                ->where('dealer_id', $dealer->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->toArray(),
            'regions' => Shop::query()
                ->forDealer($dealer->id)
                ->whereNotNull('region')
                ->distinct()
                ->pluck('region')
                ->filter()
                ->values()
                ->all(),
            'placeholders' => [
                '{shop_name}', '{shop_phone}', '{contact_person}', '{member_name}',
                '{balance}', '{dealer_name}', '{date}', '{time}',
            ],
            'audience_types' => $this->audienceOptions(),
            'schedule_types' => $this->scheduleOptions(),
            'media_types' => array_map(
                fn (BroadcastMediaType $t): array => ['value' => $t->value, 'label' => $t->label()],
                BroadcastMediaType::cases(),
            ),
            'timezone' => 'Asia/Tashkent',
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function audienceOptions(): array
    {
        return [
            ['value' => BroadcastAudienceType::ALL_ACTIVE->value, 'label' => BroadcastAudienceType::ALL_ACTIVE->label()],
            ['value' => BroadcastAudienceType::SELECTED_SHOPS->value, 'label' => BroadcastAudienceType::SELECTED_SHOPS->label()],
            ['value' => BroadcastAudienceType::FILTER->value, 'label' => BroadcastAudienceType::FILTER->label()],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function scheduleOptions(): array
    {
        return array_map(
            fn (BroadcastScheduleType $t): array => ['value' => $t->value, 'label' => $t->label()],
            BroadcastScheduleType::cases(),
        );
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
