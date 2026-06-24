<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\RegisterDealerAction;
use App\Actions\UpdateDealerCommissionAction;
use App\Contracts\WebhookServiceInterface;
use App\Enums\CommissionType;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignDirectoryShopsRequest;
use App\Http\Requests\Admin\StoreDealerRequest;
use App\Http\Requests\Admin\StorePlatformPaymentRequest;
use App\Http\Requests\Admin\UpdateDealerCommissionRequest;
use App\Http\Requests\Admin\UpdateDealerRequest;
use App\Http\Resources\CountryResource;
use App\Http\Resources\DealerResource;
use App\Models\Country;
use App\Models\Dealer;
use App\Models\DirectoryShop;
use App\Models\PlatformPayment;
use App\Models\Shop;
use App\Services\AuditLogger;
use App\Services\CommissionHistoryService;
use App\Support\Translit;
use App\Support\UzRegions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class DealerController extends Controller
{
    public function __construct(
        private readonly RegisterDealerAction $registerDealerAction,
        private readonly UpdateDealerCommissionAction $updateCommissionAction,
        private readonly WebhookServiceInterface $webhookService,
        private readonly AuditLogger $audit,
        private readonly CommissionHistoryService $commissionHistory,
    ) {}

    public function index(): Response
    {
        $dealers = Dealer::query()
            ->withCount(['shops', 'orders', 'products'])
            ->withSum(['orders as revenue' => fn ($q) => $q->fulfilled()], 'total')
            ->orderByDesc('is_self_registered') // o'zi ro'yxatdan o'tganlar tepada
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Dealers/Index', [
            'dealers' => DealerResource::collection($dealers),
            'totals' => [
                'dealers' => Dealer::query()->count(),
                'active' => Dealer::query()->active()->count(),
                'self_registered' => Dealer::query()->where('is_self_registered', true)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Dealers/Create', [
            'countries' => CountryResource::collection(Country::query()->active()->ordered()->get())->resolve(),
        ]);
    }

    public function store(StoreDealerRequest $request): RedirectResponse
    {
        $dealer = $this->registerDealerAction->execute($request->validated());

        $webhookOk = $this->webhookService->register($dealer);

        $this->audit->log('dealer.created', $dealer, [
            'name' => $dealer->name,
            'webhook_ok' => $webhookOk,
        ]);

        $message = match (true) {
            $webhookOk => 'Diller yaratildi va webhook muvaffaqiyatli o\'rnatildi',
            $dealer->bot_token === null => 'Diller yaratildi. Bot tokenini keyinroq qo\'shishingiz mumkin.',
            default => 'Diller yaratildi, lekin webhook o\'rnatilmadi — tokenni tekshiring',
        };

        return redirect()
            ->route('admin.dealers.index')
            ->with('status', $message);
    }

    public function edit(Dealer $dealer): Response
    {
        $dealer->load('owner:id,dealer_id,username');

        return Inertia::render('Admin/Dealers/Edit', [
            'dealer' => DealerResource::make($dealer),
            'dealerUsername' => $dealer->owner?->username ?? '',
            'webhook' => $this->buildWebhookSnapshot($dealer),
            'shopsCount' => $dealer->shops()->count(),
            'regions' => UzRegions::all(),
            'countries' => CountryResource::collection(Country::query()->active()->ordered()->get())->resolve(),
        ]);
    }

    /**
     * Spravochnikdan dealerga biriktirish uchun mijozlarni qidiradi.
     * Shu dealerga allaqachon biriktirilgan yozuvlar ro'yxatga chiqmaydi.
     * Viloyat / tuman bo'yicha ham filtr qilinadi.
     */
    public function directorySearch(Request $request, Dealer $dealer): JsonResponse
    {
        $search = trim((string) $request->string('q'));
        $region = trim((string) $request->string('region'));
        $district = trim((string) $request->string('district'));
        $offset = max(0, $request->integer('offset'));
        $limit = 100;

        $ownedIds = $dealer->shops()
            ->whereNotNull('directory_id')
            ->pluck('directory_id')
            ->all();

        $entries = DirectoryShop::query()
            ->when($ownedIds !== [], fn ($q) => $q->whereNotIn('id', $ownedIds))
            ->when($region !== '', fn ($q) => $q->where('region', $region))
            ->when($district !== '', fn ($q) => $q->where('district', $district))
            ->when($search !== '', function ($q) use ($search): void {
                $digits = preg_replace('/\D+/', '', $search) ?? '';

                $q->where(function ($q2) use ($search, $digits): void {
                    Translit::applyLike($q2, ['name', 'legal_name', 'inn'], $search);

                    if (strlen($digits) >= 4) {
                        $q2->orWhere('phone_normalized', 'ilike', '%'.substr($digits, -9).'%');
                    }
                });
            })
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'shops' => $entries->map(fn (DirectoryShop $entry): array => [
                'id' => $entry->id,
                'name' => $entry->name,
                'legal_name' => $entry->legal_name,
                'inn' => $entry->inn,
                'phone' => $entry->phone,
                'region' => $entry->region,
                'district' => $entry->district,
            ])->values()->all(),
            'has_more' => $entries->count() === $limit,
        ]);
    }

    /**
     * Tanlangan spravochnik yozuvlaridan shu dealer uchun shop'lar yaratadi.
     * Allaqachon ulangan yozuvlar o'tkazib yuboriladi (dedup directory_id bo'yicha).
     */
    public function assignShops(AssignDirectoryShopsRequest $request, Dealer $dealer): JsonResponse
    {
        $requestedIds = $request->validated('directory_ids');

        $existingIds = $dealer->shops()
            ->whereIn('directory_id', $requestedIds)
            ->pluck('directory_id')
            ->all();

        $newIds = array_values(array_diff($requestedIds, $existingIds));

        if ($newIds === []) {
            return response()->json([
                'created' => 0,
                'shops_count' => $dealer->shops()->count(),
                'message' => 'Tanlangan mijozlar allaqachon biriktirilgan',
            ]);
        }

        $entries = DirectoryShop::query()->whereIn('id', $newIds)->get();
        $now = now()->toDateTimeString();

        // Bitta bulk insert — directory_id allaqachon aniq, shu sabab ShopObserver
        // (syncFromShop) kerak emas. Bu har shop uchun ortiqcha query'larni yo'q qiladi.
        $rows = $entries->map(fn (DirectoryShop $entry): array => [
            'dealer_id' => $dealer->id,
            'directory_id' => $entry->id,
            'name' => $entry->name,
            'legal_name' => $entry->legal_name,
            'phone' => $entry->phone,
            'address' => $entry->address,
            'landmark' => $entry->landmark,
            'region' => $entry->region,
            'district' => $entry->district,
            'inn' => $entry->inn,
            'contact_person' => $entry->contact_person,
            'photo' => $this->copyDirectoryPhoto($entry, $dealer),
            'latitude' => $entry->latitude,
            'longitude' => $entry->longitude,
            'balance' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::transaction(fn () => Shop::query()->insert($rows));

        $created = count($rows);

        $this->audit->log('dealer.shops.assigned', $dealer, [
            'dealer_name' => $dealer->name,
            'directory_ids' => $newIds,
            'created' => $created,
        ]);

        return response()->json([
            'created' => $created,
            'shops_count' => $dealer->shops()->count(),
            'message' => "{$created} ta mijoz \"{$dealer->name}\" dilleriga biriktirildi",
        ]);
    }

    /**
     * Spravochnik rasmini dealer papkasiga nusxalaydi (ulashilgan faylni
     * o'chirib yubormaslik uchun). Rasm yo'q bo'lsa null qaytaradi.
     */
    private function copyDirectoryPhoto(DirectoryShop $entry, Dealer $dealer): ?string
    {
        if ($entry->photo === null || $entry->photo === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($entry->photo)) {
            return null;
        }

        $newPath = "dealers/{$dealer->id}/shops/".Str::uuid()->toString().'.'.pathinfo($entry->photo, PATHINFO_EXTENSION);
        $disk->copy($entry->photo, $newPath);

        return $newPath;
    }

    public function update(UpdateDealerRequest $request, Dealer $dealer): RedirectResponse
    {
        $validated = $request->validated();
        $oldToken = $dealer->bot_token;

        $dealer->update(Arr::only($validated, [
            'name', 'bot_token', 'telegram_chat_id', 'min_order_amount',
            'country_id', 'currency',
            'sells_on_marketplace', 'marketplace_commission_type',
            'marketplace_platform_fee_rate', 'marketplace_fixed_commission_amount',
        ]));

        $user = $dealer->owner;
        if ($user !== null) {
            $userData = [];

            if (! empty($validated['name'])) {
                $userData['name'] = $validated['name'];
            }
            if (! empty($validated['username'])) {
                $userData['username'] = $validated['username'];
            }
            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            if ($userData !== []) {
                $user->update($userData);
            }
        }

        // Token o'zgargan — webhook qayta o'rnatish
        if (isset($validated['bot_token']) && $validated['bot_token'] !== $oldToken) {
            $this->webhookService->register($dealer->fresh());
        }

        return redirect()
            ->route('admin.dealers.index')
            ->with('status', "Diller \"{$dealer->name}\" yangilandi");
    }

    public function destroy(Dealer $dealer): RedirectResponse
    {
        $snapshot = ['name' => $dealer->name, 'bot_username' => $dealer->bot_username];

        $this->webhookService->remove($dealer);
        $dealer->users()->delete();
        $dealer->delete();

        $this->audit->log('dealer.deleted', null, $snapshot);

        return redirect()
            ->route('admin.dealers.index')
            ->with('status', 'Diller o\'chirildi');
    }

    public function toggleActive(Dealer $dealer): RedirectResponse
    {
        $dealer->update(['is_active' => ! $dealer->is_active]);

        if ($dealer->is_active) {
            $this->webhookService->register($dealer);
        } else {
            $this->webhookService->remove($dealer);
        }

        $this->audit->log('dealer.toggled', $dealer, ['is_active' => $dealer->is_active]);

        $label = $dealer->is_active ? 'faollashtirildi' : 'o\'chirildi';

        return back()->with('status', "Diller \"{$dealer->name}\" {$label}");
    }

    public function setWebhook(Dealer $dealer): RedirectResponse
    {
        $ok = $this->webhookService->register($dealer);

        $msg = $ok
            ? 'Webhook muvaffaqiyatli o\'rnatildi'
            : 'Webhook o\'rnatilmadi — tokenni va URL ni tekshiring';

        return back()->with('status', $msg);
    }

    public function removeWebhook(Dealer $dealer): RedirectResponse
    {
        $ok = $this->webhookService->remove($dealer);

        $msg = $ok
            ? 'Webhook o\'chirildi'
            : 'Webhookni o\'chirishda xatolik yuz berdi';

        return back()->with('status', $msg);
    }

    public function updateCommission(UpdateDealerCommissionRequest $request, Dealer $dealer): RedirectResponse
    {
        $type = CommissionType::from((string) $request->input('commission_type'));

        $before = [
            'commission_type' => ($dealer->commission_type ?? CommissionType::TURNOVER_PERCENTAGE)->value,
            'platform_fee_rate' => (float) $dealer->platform_fee_rate,
            'fixed_commission_amount' => $dealer->fixed_commission_amount,
        ];

        $this->updateCommissionAction->execute(
            dealer: $dealer,
            type: $type,
            percentageRate: $request->has('platform_fee_rate') ? $request->float('platform_fee_rate') : null,
            fixedAmount: $request->has('fixed_commission_amount') ? $request->integer('fixed_commission_amount') : null,
        );

        $fresh = $dealer->fresh();
        $after = [
            'commission_type' => $fresh->commission_type->value,
            'platform_fee_rate' => (float) $fresh->platform_fee_rate,
            'fixed_commission_amount' => $fresh->fixed_commission_amount,
        ];

        $this->audit->log('dealer.commission.updated', $dealer, [
            'before' => $before,
            'after' => $after,
        ]);

        $message = match ($type) {
            CommissionType::TURNOVER_PERCENTAGE => "\"{$dealer->name}\" komissiyasi yangilandi — {$after['platform_fee_rate']}%",
            CommissionType::FIXED_PER_SHOP => "\"{$dealer->name}\" komissiyasi yangilandi — har mijoz uchun ".number_format((int) $after['fixed_commission_amount'], 0, '.', ' ')." so'm",
            CommissionType::FIXED_PER_ORDER => "\"{$dealer->name}\" komissiyasi yangilandi — har buyurtma uchun ".number_format((int) $after['fixed_commission_amount'], 0, '.', ' ')." so'm",
            CommissionType::FIXED_PER_DELIVERYMAN => "\"{$dealer->name}\" komissiyasi yangilandi — har yetkazib beruvchi uchun ".number_format((int) $after['fixed_commission_amount'], 0, '.', ' ')." so'm",
            CommissionType::FIXED_MONTHLY => "\"{$dealer->name}\" komissiyasi yangilandi — oyiga ".number_format((int) $after['fixed_commission_amount'], 0, '.', ' ')." so'm",
        };

        return back()->with('status', $message);
    }

    public function storePlatformPayment(StorePlatformPaymentRequest $request, Dealer $dealer): RedirectResponse
    {
        $payment = PlatformPayment::query()->create([
            'dealer_id' => $dealer->id,
            'currency' => $dealer->currency ?? Currency::UZS,
            'amount' => $request->integer('amount'),
            'discount' => $request->integer('discount'),
            'note' => $request->input('note'),
        ]);

        $this->audit->log('platform_payment.created', $payment, [
            'dealer_id' => $dealer->id,
            'dealer_name' => $dealer->name,
            'amount' => $payment->amount,
            'discount' => $payment->discount,
            'note' => $payment->note,
        ]);

        $this->commissionHistory->invalidate();

        return back()->with('status', "\"{$dealer->name}\" uchun to'lov qayd qilindi");
    }

    public function destroyPlatformPayment(PlatformPayment $payment): RedirectResponse
    {
        $snapshot = [
            'dealer_id' => $payment->dealer_id,
            'amount' => $payment->amount,
            'discount' => $payment->discount,
            'note' => $payment->note,
        ];

        $payment->delete();

        $this->audit->log('platform_payment.deleted', null, $snapshot);

        $this->commissionHistory->invalidate();

        return back()->with('status', 'To\'lov yozuvi o\'chirildi');
    }

    /**
     * @return array{
     *     expected_url: string,
     *     set_at: string|null,
     *     telegram: array{url: string, pending_update_count: int, last_error_message: string|null, last_error_date: int|null}|null,
     *     matches_expected: bool
     * }
     */
    private function buildWebhookSnapshot(Dealer $dealer): array
    {
        $expected = $this->webhookService->url($dealer);
        $info = $this->webhookService->getInfo($dealer);

        return [
            'expected_url' => $expected,
            'set_at' => $dealer->webhook_set_at?->toIso8601String(),
            'telegram' => $info,
            'matches_expected' => $info !== null && $info['url'] === $expected,
        ];
    }
}
