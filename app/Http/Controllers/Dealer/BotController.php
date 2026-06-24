<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Contracts\WebhookServiceInterface;
use App\Enums\BotVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\UpdateBotRequest;
use App\Http\Resources\DealerResource;
use App\Models\Dealer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BotController extends Controller
{
    public function __construct(
        private readonly WebhookServiceInterface $webhookService,
    ) {}

    public function show(Request $request): Response
    {
        $dealer = $this->dealer($request);

        // Token bor, lekin Telegram'da webhook haqiqatan o'rnatilmagan bo'lsa —
        // sahifa ochilganda avtomatik o'rnatamiz (DB'dagi webhook_set_at flag emas,
        // Telegram'ning real holatiga qaraymiz).
        $snapshot = $this->buildWebhookSnapshot($dealer);

        if ($dealer->bot_token !== null && ($snapshot['telegram']['url'] ?? '') === '') {
            $this->webhookService->register($dealer);
            $dealer = $dealer->fresh() ?? $dealer;
            $snapshot = $this->buildWebhookSnapshot($dealer);
        }

        // Bildirishnoma chatini ulash uchun bir martalik deep-link (bot ulangan bo'lsa).
        $notifyConnectUrl = $dealer->bot_username !== null
            ? 'https://t.me/'.$dealer->bot_username.'?start='.$dealer->ensureOwnerLinkToken()
            : null;

        return Inertia::render('Dealer/Bot/Index', [
            'dealer' => DealerResource::make($dealer),
            'webhook' => $snapshot,
            'miniapp_url' => route('miniapp', ['dealer' => $dealer->id]),
            'notify_connect_url' => $notifyConnectUrl,
        ]);
    }

    /**
     * Buyurtma va ko'rinish sozlamalari (bot + mobil ilova uchun umumiy).
     * Alohida menu sahifasi; saqlash xuddi shu update() endpointiga boradi.
     */
    public function orderSettings(Request $request): Response
    {
        $dealer = $this->dealer($request);

        return Inertia::render('Dealer/Settings/OrderSettings', [
            // resolve() — 'data' o'ramisiz tekis massiv (props.dealer.visibility to'g'ri o'qilsin).
            'dealer' => DealerResource::make($dealer)->resolve($request),
            'miniapp_url' => route('miniapp', ['dealer' => $dealer->id]),
        ]);
    }

    public function update(UpdateBotRequest $request): RedirectResponse
    {
        $dealer = $this->dealer($request);
        $validated = $request->validated();
        $oldToken = $dealer->bot_token;
        $oldProfile = $dealer->only(['bot_display_name', 'bot_short_description', 'bot_description']);

        $updates = collect($validated)
            ->only(['bot_token', 'telegram_chat_id'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        if (array_key_exists('min_order_amount', $validated)) {
            $updates['min_order_amount'] = (int) ($validated['min_order_amount'] ?? 0);
        }

        if (array_key_exists('marketplace_min_order_amount', $validated)) {
            $updates['marketplace_min_order_amount'] = (int) ($validated['marketplace_min_order_amount'] ?? 0);
        }

        if (array_key_exists('show_out_of_stock', $validated)) {
            $updates['show_out_of_stock'] = (bool) $validated['show_out_of_stock'];
        }

        if (array_key_exists('notify_on_price_change', $validated)) {
            $updates['notify_on_price_change'] = (bool) $validated['notify_on_price_change'];
        }

        if (array_key_exists('notify_on_new_product', $validated)) {
            $updates['notify_on_new_product'] = (bool) $validated['notify_on_new_product'];
        }

        if (array_key_exists('visibility', $validated)) {
            $updates['visibility'] = BotVisibility::from((string) $validated['visibility']);
        }

        foreach (['bot_display_name', 'bot_short_description', 'bot_description', 'contact_phone'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value = is_string($validated[$field]) ? trim($validated[$field]) : null;
                $updates[$field] = $value === '' ? null : $value;
            }
        }

        if ($updates !== []) {
            $dealer->update($updates);
        }

        $dealer = $dealer->fresh();
        $newToken = $validated['bot_token'] ?? null;
        $tokenChanged = $newToken !== null && $newToken !== '' && $newToken !== $oldToken;

        $webhookTouched = false;

        if ($tokenChanged) {
            // Username'ni webhook'dan mustaqil saqlaymiz — setWebhook (HTTPS/cert)
            // har qanday sababga ko'ra fail bo'lsa ham bot @username ko'rinadi.
            $username = $this->webhookService->verifyToken($newToken);
            if ($username !== null && $username !== $dealer->bot_username) {
                $dealer->forceFill(['bot_username' => $username])->save();
            }

            $this->webhookService->register($dealer);
            $webhookTouched = true;
        } elseif ($dealer->bot_token !== null && $dealer->webhook_set_at === null) {
            // Token bor, lekin webhook hali o'rnatilmagan — saqlashda avtomatik o'rnatamiz.
            $this->webhookService->register($dealer);
            $webhookTouched = true;
        } elseif ($this->profileChanged($oldProfile, $dealer)) {
            $this->webhookService->applyProfile($dealer);
        }

        // Webhook ogohlantirishi faqat token/webhook tegilganda — buyurtma
        // sozlamalari saqlanganda webhook haqida gap chiqmasligi kerak.
        $message = 'Sozlamalar yangilandi';
        if ($webhookTouched) {
            $webhookOk = $this->webhookService->getInfo($dealer->fresh() ?? $dealer);
            if (($webhookOk['url'] ?? '') === '') {
                $message = 'Saqlandi, lekin webhook o\'rnatilmadi — token va URL ni tekshiring';
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /** @param array<string, mixed> $old */
    private function profileChanged(array $old, Dealer $dealer): bool
    {
        return $old['bot_display_name'] !== $dealer->bot_display_name
            || $old['bot_short_description'] !== $dealer->bot_short_description
            || $old['bot_description'] !== $dealer->bot_description;
    }

    public function setWebhook(Request $request): RedirectResponse
    {
        $ok = $this->webhookService->register($this->dealer($request));

        Inertia::flash('toast', [
            'type' => $ok ? 'success' : 'error',
            'message' => $ok
                ? 'Webhook muvaffaqiyatli o\'rnatildi'
                : 'Webhook o\'rnatilmadi — tokenni va URL ni tekshiring',
        ]);

        return back();
    }

    public function removeWebhook(Request $request): RedirectResponse
    {
        $ok = $this->webhookService->remove($this->dealer($request));

        Inertia::flash('toast', [
            'type' => $ok ? 'success' : 'error',
            'message' => $ok
                ? 'Webhook o\'chirildi'
                : 'Webhookni o\'chirishda xatolik yuz berdi',
        ]);

        return back();
    }

    public function verifyToken(Request $request): JsonResponse
    {
        $token = $request->string('token')->toString();

        if ($token === '') {
            return response()->json(['username' => null, 'taken_by' => null]);
        }

        // Avval bazadan qidiramiz — band token uchun Telegram API ga so'rov yubormaslik.
        $currentDealerId = (int) $request->user()?->dealer_id;
        $existing = Dealer::query()
            ->where('bot_token', $token)
            ->where('id', '!=', $currentDealerId)
            ->first(['name', 'bot_username']);

        if ($existing !== null) {
            return response()->json([
                'username' => $existing->bot_username,
                'taken_by' => $existing->name,
            ]);
        }

        $username = $this->webhookService->verifyToken($token);

        return response()->json([
            'username' => $username,
            'taken_by' => null,
        ]);
    }

    private function dealer(Request $request): Dealer
    {
        $dealerId = (int) $request->user()?->dealer_id;
        abort_if($dealerId === 0, 403);

        return Dealer::query()->findOrFail($dealerId);
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
