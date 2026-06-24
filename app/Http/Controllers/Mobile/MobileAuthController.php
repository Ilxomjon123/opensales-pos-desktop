<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Exceptions\Domain\InvalidInviteException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\MobileAppLinkService;
use App\Services\MobileQrLoginService;
use App\Services\MobileRegistrationService;
use App\Services\OtpService;
use App\Services\ShopInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobil ilova autentifikatsiyasi. Asosiy kirish — telefon + OTP.
 * Qo'shimcha ulanish: o'z-ro'yxati (fan-out), invite skan, Telegram ulash.
 */
final class MobileAuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly MobileAppLinkService $linkService,
        private readonly MobileRegistrationService $registration,
        private readonly ShopInviteService $inviteService,
        private readonly MobileQrLoginService $qrLoginService,
    ) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:7', 'max:20'],
        ]);

        $sent = $this->otp->request($validated['phone']);

        if (! $sent) {
            return response()->json(['message' => 'Biroz kuting, kod yaqinda yuborilgan'], 429);
        }

        return response()->json(['message' => 'Kod yuborildi']);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:7', 'max:20'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $this->otp->verify($validated['phone'], $validated['code'])) {
            return response()->json(['message' => 'Kod noto\'g\'ri yoki muddati o\'tgan'], 422);
        }

        $phone = $this->otp->normalize($validated['phone']);

        $customer = Customer::query()->firstOrCreate(['phone' => $phone], ['is_active' => true]);

        if (! $customer->is_active) {
            return response()->json(['message' => 'Akkaunt bloklangan'], 403);
        }

        $customer->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $customer->createToken('mobile')->plainTextToken,
            'customer' => [
                'id' => $customer->id,
                'phone' => $customer->phone,
                'name' => $customer->name,
            ],
            'has_membership' => $customer->shopMembers()->active()->exists(),
        ], 201);
    }

    /**
     * O'z-ro'yxati: joylashuvni qoplaydigan har bir ochiq dillerga a'zo bo'ladi.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'min:2', 'max:255'],
            'address' => ['required', 'string', 'min:3', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Customer $customer */
        $customer = $request->user();

        if (($validated['name'] ?? null) !== null && $customer->name === null) {
            $customer->forceFill(['name' => $validated['name']])->save();
        }

        $members = $this->registration->register(
            customer: $customer,
            shopName: $validated['shop_name'],
            address: $validated['address'],
            latitude: $validated['latitude'] ?? null,
            longitude: $validated['longitude'] ?? null,
            region: $validated['region'] ?? null,
            district: $validated['district'] ?? null,
        );

        return response()->json([
            'joined' => $members->count(),
            'dealers' => $members
                ->map(fn ($m) => [
                    'dealer_id' => $m->shop?->dealer_id,
                    'shop_id' => $m->shop_id,
                    'shop_name' => $m->shop?->name,
                ])
                ->values(),
        ], 201);
    }

    /**
     * Scan: diller invite tokeni → yangi shop/diller a'zoligi (mavjud akkauntga).
     */
    public function redeemInvite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        /** @var Customer $customer */
        $customer = $request->user();

        try {
            $member = $this->inviteService->redeem(
                token: $validated['token'],
                customerId: $customer->id,
                name: $customer->name,
            );
        } catch (InvalidInviteException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $shop = $member->shop()->with('dealer')->first();

        return response()->json([
            'shop' => [
                'id' => $shop?->id,
                'name' => $shop?->name,
                'dealer' => [
                    'id' => $shop?->dealer?->id,
                    'name' => $shop?->dealer?->name,
                ],
            ],
        ], 201);
    }

    /**
     * Login sahifasidan QR (diller invite) orqali PAROLSIZ kirish: shop telefoni
     * bo'yicha akkaunt topiladi/yaratiladi va o'sha shopga ulanadi. Token qaytaradi.
     */
    public function qrLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            ['customer' => $customer, 'shop' => $shop] = $this->qrLoginService->login($validated['token']);
        } catch (InvalidInviteException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $customer->is_active) {
            return response()->json(['message' => 'Akkaunt bloklangan'], 403);
        }

        $customer->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $customer->createToken('mobile')->plainTextToken,
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'dealer' => [
                    'id' => $shop->dealer?->id,
                    'name' => $shop->dealer?->name,
                ],
            ],
            'has_membership' => true,
        ], 201);
    }

    /**
     * Telegram ulash: botdagi kod → bot vakillarini shu akkauntga birlashtiradi.
     */
    public function linkTelegram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        /** @var Customer $customer */
        $customer = $request->user();

        if (! $this->linkService->consume($validated['code'], $customer)) {
            return response()->json(['message' => 'Kod yaroqsiz yoki muddati o\'tgan'], 422);
        }

        return response()->json(['message' => 'Telegram ulandi']);
    }

    /**
     * Joriy mijoz haqida (Sozlamalar uchun): telefon, ism, Telegram ulanganmi.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json([
            'phone' => $customer->phone,
            'name' => $customer->name,
            'telegram_linked' => $customer->shopMembers()->whereNotNull('telegram_id')->exists(),
        ]);
    }

    /**
     * App tili o'zgarganda — mijoz tilini saqlaymiz (bildirishnomalar shu tilda).
     */
    public function setLocale(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', 'max:10']]);
        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        // Chiqishdan oldin shu qurilma tokenini o'chiramiz (push kelmasin).
        if (is_string($token = $request->input('device_token')) && $token !== '') {
            $customer->deviceTokens()->where('token', $token)->delete();
        }

        $customer->currentAccessToken()?->delete();

        return response()->json(['message' => 'Chiqildi']);
    }
}
