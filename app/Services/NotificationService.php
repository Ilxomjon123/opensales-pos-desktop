<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\AppNotification;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Product;
use App\Models\ShopMember;
use App\Services\Fcm\FcmSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Mobil ilova bildirishnomalari: feed yozuvini (app_notifications) yaratadi va
 * mijozning qurilmalariga FCM push yuboradi. Manba — zakas va katalog hodisalari.
 * Matn har mijozning tiliga (locale) moslab quriladi.
 */
final class NotificationService
{
    public function __construct(private readonly FcmSender $fcm) {}

    /**
     * Ommaviy/rejalashtirilgan xabar — auditoriyaning mobil mijozlariga feed + FCM.
     * Har a'zo uchun matn alohida render qilinadi (placeholderlar). RAM-safe: chunklab insert.
     *
     * @param  iterable<ShopMember>  $members
     * @param  callable(ShopMember): string  $bodyFor
     */
    public function broadcast(string $title, iterable $members, callable $bodyFor): void
    {
        $orig = app()->getLocale();
        $now = now();
        $rows = [];

        /** @var array<string, array{title: string, body: string, tokens: list<string>}> $groups */
        $groups = [];

        $flush = function () use (&$rows): void {
            if ($rows !== []) {
                AppNotification::query()->insert($rows);
                $rows = [];
            }
        };

        foreach ($members as $member) {
            $customer = $member->customer;

            if ($customer === null) {
                continue;
            }

            app()->setLocale($customer->locale ?? $member->locale ?? $orig);

            $body = $bodyFor($member);
            $dealerId = $member->shop?->dealer_id;
            $data = ['type' => 'broadcast'];
            if ($dealerId !== null) {
                $data['dealer_id'] = (string) $dealerId;
            }

            $rows[] = [
                'customer_id' => $customer->id,
                'dealer_id' => $dealerId,
                'order_id' => null,
                'type' => 'broadcast',
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                $flush();
            }

            $tokens = $customer->relationLoaded('deviceTokens')
                ? $customer->deviceTokens->pluck('token')->all()
                : $customer->deviceTokens()->pluck('token')->all();

            if ($tokens !== []) {
                $key = $title."\n".$body;
                $groups[$key] ??= ['title' => $title, 'body' => $body, 'tokens' => []];
                $groups[$key]['tokens'] = array_merge($groups[$key]['tokens'], $tokens);
            }
        }

        $flush();
        app()->setLocale($orig);

        foreach ($groups as $group) {
            $this->fcm->send($group['tokens'], $group['title'], $group['body'], ['type' => 'broadcast']);
        }
    }

    // ===== Buyurtma hodisalari (bitta shop a'zolariga) =====

    public function orderCreated(Order $order): void
    {
        $this->dispatchOrder($order, 'order_created');
    }

    public function orderStatus(Order $order, OrderStatus $to): void
    {
        $this->dispatchOrder($order, 'order_status', ['status' => $to]);
    }

    public function orderEdited(Order $order): void
    {
        $this->dispatchOrder($order, 'order_edited');
    }

    public function orderMessage(OrderMessage $message): void
    {
        $order = $message->order;

        if ($order !== null) {
            $this->dispatchOrder($order, 'order_message', ['message' => $message]);
        }
    }

    /**
     * Xabar o'chirilganda — unga tegishli feed yozuvlarini ham olib tashlaymiz.
     */
    public function removeOrderMessage(int $messageId): void
    {
        AppNotification::query()
            ->where('type', 'order_message')
            ->where('data->message_id', (string) $messageId)
            ->delete();
    }

    // ===== Katalog hodisalari (diller barcha mijozlariga broadcast) =====

    public function productCreated(Product $product): void
    {
        $this->broadcastProduct($product, 'product_new');
    }

    /**
     * @param  array{old: float, new: float, oldPack: ?float, newPack: ?float}  $prices
     */
    public function productPriceChanged(Product $product, array $prices): void
    {
        $this->broadcastProduct($product, 'product_price', $prices);
    }

    // ===== Ichki yetkazish =====

    /**
     * Bitta zakas shop a'zolariga (customer_id bor) feed + push.
     *
     * @param  array{status?: OrderStatus, message?: OrderMessage}  $ctx
     */
    private function dispatchOrder(Order $order, string $type, array $ctx = []): void
    {
        $order->loadMissing('shop.members.customer');

        $members = ($order->shop?->members ?? collect())
            ->where('is_active', true)
            ->whereNotNull('customer_id')
            ->unique('customer_id');

        $data = [
            'type' => $type,
            'dealer_id' => (string) $order->dealer_id,
            'order_id' => (string) $order->id,
            'shop_id' => (string) $order->shop_id,
        ];

        if (isset($ctx['message'])) {
            $data['message_id'] = (string) $ctx['message']->id;
        }

        $this->deliver(
            members: $members,
            type: $type,
            dealerId: (int) $order->dealer_id,
            orderId: (int) $order->id,
            data: $data,
            text: fn (): array => $this->orderText($order, $type, $ctx),
        );
    }

    /**
     * Diller barcha do'konlarining a'zolariga (customer_id bor) broadcast.
     *
     * @param  array{old?: float, new?: float, oldPack?: ?float, newPack?: ?float}  $ctx
     */
    private function broadcastProduct(Product $product, string $type, array $ctx = []): void
    {
        /** @var Dealer|null $dealer */
        $dealer = $product->loadMissing('dealer')->dealer;

        if ($dealer === null || ! $product->is_active) {
            return;
        }

        $members = ShopMember::query()
            ->where('is_active', true)
            ->whereNotNull('customer_id')
            ->whereHas('shop', fn ($q) => $q->where('dealer_id', $dealer->id))
            ->with('customer')
            ->get()
            ->unique('customer_id');

        $data = [
            'type' => $type,
            'dealer_id' => (string) $dealer->id,
            'product_id' => (string) $product->id,
        ];

        $this->deliver(
            members: $members,
            type: $type,
            dealerId: (int) $dealer->id,
            orderId: null,
            data: $data,
            text: fn (): array => $this->productText($product, $type, $ctx),
        );
    }

    /**
     * Umumiy yetkazish: har customer uchun feed yozuvi + locale bo'yicha
     * guruhlangan FCM push.
     *
     * @param  Collection<int, ShopMember>  $members
     * @param  array<string, string>  $data
     * @param  callable(): array{0: string, 1: string}  $text
     */
    private function deliver(
        Collection $members,
        string $type,
        int $dealerId,
        ?int $orderId,
        array $data,
        callable $text,
    ): void {
        if ($members->isEmpty()) {
            return;
        }

        $orig = app()->getLocale();

        /** @var array<string, array{title: string, body: string, tokens: list<string>}> $groups */
        $groups = [];
        $rows = [];
        $now = now();

        foreach ($members as $member) {
            $customer = $member->customer;

            if ($customer === null) {
                continue;
            }

            app()->setLocale($customer->locale ?? $member->locale ?? $orig);

            [$title, $body] = $text();

            $rows[] = [
                'customer_id' => $customer->id,
                'dealer_id' => $dealerId,
                'order_id' => $orderId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $tokens = $customer->deviceTokens()->pluck('token')->all();

            if ($tokens === []) {
                continue;
            }

            $key = $title."\n".$body;
            $groups[$key] ??= ['title' => $title, 'body' => $body, 'tokens' => []];
            $groups[$key]['tokens'] = array_merge($groups[$key]['tokens'], $tokens);
        }

        app()->setLocale($orig);

        if ($rows !== []) {
            AppNotification::query()->insert($rows);
        }

        foreach ($groups as $group) {
            $this->fcm->send($group['tokens'], $group['title'], $group['body'], $data);
        }
    }

    /**
     * @param  array{status?: OrderStatus, message?: OrderMessage}  $ctx
     * @return array{0: string, 1: string}
     */
    private function orderText(Order $order, string $type, array $ctx): array
    {
        $number = $order->displayNumber();

        return match ($type) {
            'order_created' => [
                __('notif.order_created.title'),
                __('notif.order_created.body', [
                    'number' => $number,
                    'amount' => Number::format($order->displayTotal()),
                ]),
            ],
            'order_status' => [
                __('notif.order_status.title', ['number' => $number]),
                ($ctx['status'] ?? OrderStatus::PENDING)->label(),
            ],
            'order_edited' => [
                __('notif.order_edited.title'),
                __('notif.order_edited.body', ['number' => $number]),
            ],
            'order_message' => [
                __('notif.order_message.title', ['number' => $number]),
                Str::limit((string) ($ctx['message']?->body ?? ''), 120),
            ],
            default => [config('app.name', 'OpenSales'), ''],
        };
    }

    /**
     * @param  array{old?: float, new?: float, oldPack?: ?float, newPack?: ?float}  $ctx
     * @return array{0: string, 1: string}
     */
    private function productText(Product $product, string $type, array $ctx): array
    {
        $name = (string) $product->name;

        return match ($type) {
            'product_new' => [
                __('notif.product_new.title'),
                __('notif.product_new.body', [
                    'name' => $name,
                    'amount' => Number::format((int) round((float) $product->price)),
                ]),
            ],
            'product_price' => [
                __('notif.product_price.title'),
                __('notif.product_price.body', [
                    'name' => $name,
                    'old' => Number::format((int) round((float) ($ctx['old'] ?? 0))),
                    'new' => Number::format((int) round((float) ($ctx['new'] ?? 0))),
                ]),
            ],
            default => [config('app.name', 'OpenSales'), ''],
        };
    }
}
