<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BroadcastAudienceType;
use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\AuditLogger;
use App\Services\PlatformBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformBroadcastController extends Controller
{
    public function __construct(
        private readonly PlatformBroadcastService $broadcast,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Broadcasts/Index', [
            'options' => $this->formOptions(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        [$type, $config] = $this->audience($request);

        return response()->json(['count' => $this->broadcast->recipientCount($type, $config)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['message' => ['required', 'string', 'min:1', 'max:4000']]);
        [$type, $config] = $this->audience($request);

        $count = $this->broadcast->dispatch($request->string('message')->toString(), $type, $config);

        $this->audit->log('platform_broadcast.sent', null, [
            'audience_type' => $type->value,
            'audience_config' => $config,
            'recipient_count' => $count,
            'preview' => mb_substr($request->string('message')->toString(), 0, 200),
        ]);

        return back()->with('status', "{$count} ta qabul qiluvchiga xabar yuborish navbatga qo'yildi");
    }

    /**
     * @return array{0: BroadcastAudienceType, 1: array<string, mixed>}
     */
    private function audience(Request $request): array
    {
        $request->validate([
            'audience_type' => ['required', Rule::in([
                BroadcastAudienceType::PLATFORM_DEALERS->value,
                BroadcastAudienceType::PLATFORM_SHOP_MEMBERS->value,
            ])],
            'audience_config' => ['nullable', 'array'],
            'audience_config.dealer_ids' => ['array'],
            'audience_config.dealer_ids.*' => ['integer'],
        ]);

        $type = BroadcastAudienceType::from($request->string('audience_type')->toString());
        $config = (array) $request->input('audience_config', []);

        return [$type, $config];
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
            'dealers' => Dealer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),
            'shops' => [],
            'categories' => ProductCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),
            'regions' => Shop::query()
                ->whereNotNull('region')
                ->distinct()
                ->orderBy('region')
                ->pluck('region')
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
