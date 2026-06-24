<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Mobil ilova bildirishnomalar feed'i (dillerlar bo'ylab umumiy, customer darajasida).
 */
final class MobileNotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $notifications = AppNotification::query()
            ->forCustomer($customer->id)
            ->latest('id')
            ->paginate(30);

        return NotificationResource::collection($notifications)->additional([
            'unread_count' => $this->unreadCount($customer),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json(['unread_count' => $this->unreadCount($customer)]);
    }

    public function read(Request $request, AppNotification $notification): JsonResponse
    {
        $this->authorizeOwner($request, $notification);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Push bosib ochilganda — shu buyurtma/mahsulotga tegishli o'qilmaganlarni belgilash.
     */
    public function readByContext(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $query = AppNotification::query()->forCustomer($customer->id)->unread();

        if (! empty($data['order_id'])) {
            $query->where('order_id', $data['order_id']);
        } elseif (! empty($data['product_id'])) {
            $query->where('data->product_id', (string) $data['product_id']);
        } else {
            return response()->json(['ok' => true]);
        }

        $query->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        AppNotification::query()
            ->forCustomer($customer->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function unreadCount(Customer $customer): int
    {
        return AppNotification::query()
            ->forCustomer($customer->id)
            ->unread()
            ->count();
    }

    private function authorizeOwner(Request $request, AppNotification $notification): void
    {
        abort_if($notification->customer_id !== $request->user()?->id, 404);
    }
}
