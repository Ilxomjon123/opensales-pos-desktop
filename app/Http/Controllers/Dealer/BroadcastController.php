<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastMediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreBroadcastRequest;
use App\Models\Dealer;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\BroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class BroadcastController extends Controller
{
    public function __construct(private readonly BroadcastService $broadcast) {}

    public function index(Request $request): Response
    {
        $dealer = $this->dealer($request);

        return Inertia::render('Dealer/Broadcasts/Index', [
            'options' => $this->formOptions($dealer),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $dealer = $this->dealer($request);
        [$type, $config] = $this->audience($request);

        return response()->json([
            'count' => $this->broadcast->recipientCount($dealer, $type, $config),
        ]);
    }

    public function store(StoreBroadcastRequest $request): RedirectResponse
    {
        $dealer = $this->dealer($request);
        [$type, $config] = $this->audience($request);

        [$mediaPath, $mediaType] = $this->storeMedia($request, $dealer);

        $count = $this->broadcast->dispatch(
            dealer: $dealer,
            message: $request->validated('message'),
            type: $type,
            config: $config,
            buttons: (array) $request->validated('buttons', []),
            mediaPath: $mediaPath,
            mediaType: $mediaType,
        );

        return back()->with('status', "{$count} ta a'zoga xabar yuborish navbatga qo'yildi");
    }

    /**
     * Yuklangan media faylni saqlaydi va [path, type] qaytaradi.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function storeMedia(StoreBroadcastRequest $request, Dealer $dealer): array
    {
        $file = $request->file('media');

        if ($file === null) {
            return [null, null];
        }

        $path = $file->store("dealers/{$dealer->id}/broadcasts", 'public');

        $type = $request->validated('media_type') ?? match (true) {
            str_starts_with((string) $file->getMimeType(), 'image/') => BroadcastMediaType::PHOTO->value,
            str_starts_with((string) $file->getMimeType(), 'video/') => BroadcastMediaType::VIDEO->value,
            default => BroadcastMediaType::DOCUMENT->value,
        };

        return [$path, $type];
    }

    /**
     * @return array{0: BroadcastAudienceType, 1: array<string, mixed>}
     */
    private function audience(Request $request): array
    {
        $request->validate([
            'audience_type' => ['required', Rule::in([
                BroadcastAudienceType::ALL_ACTIVE->value,
                BroadcastAudienceType::SELECTED_SHOPS->value,
                BroadcastAudienceType::FILTER->value,
            ])],
            'audience_config' => ['nullable', 'array'],
        ]);

        $type = BroadcastAudienceType::from($request->string('audience_type')->toString());
        $config = (array) $request->input('audience_config', []);

        return [$type, $config];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Dealer $dealer): array
    {
        return [
            'audience_types' => [
                ['value' => BroadcastAudienceType::ALL_ACTIVE->value, 'label' => BroadcastAudienceType::ALL_ACTIVE->label()],
                ['value' => BroadcastAudienceType::SELECTED_SHOPS->value, 'label' => BroadcastAudienceType::SELECTED_SHOPS->label()],
                ['value' => BroadcastAudienceType::FILTER->value, 'label' => BroadcastAudienceType::FILTER->label()],
            ],
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
            'media_types' => array_map(
                fn (BroadcastMediaType $t): array => ['value' => $t->value, 'label' => $t->label()],
                BroadcastMediaType::cases(),
            ),
        ];
    }

    private function dealer(Request $request): Dealer
    {
        return Dealer::query()->findOrFail((int) $request->user()->dealer_id);
    }
}
