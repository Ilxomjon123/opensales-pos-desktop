<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FCM qurilma tokenlari: ro'yxatdan o'tkazish (login/ochilishda) va o'chirish (logout).
 */
final class MobileDeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        // Token boshqa customer'da bo'lsa — joriy customer'ga o'tkazamiz (qurilma almashgan).
        DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            ['customer_id' => $customer->id, 'platform' => $data['platform'] ?? 'android'],
        );

        // Bildirishnoma tili mijoz tiliga moslansin (queue'da request bo'lmaydi).
        $customer->forceFill(['locale' => $data['locale'] ?? app()->getLocale()])->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('customer_id', $request->user()?->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['ok' => true]);
    }
}
