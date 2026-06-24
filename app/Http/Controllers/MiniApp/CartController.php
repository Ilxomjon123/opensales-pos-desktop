<?php

declare(strict_types=1);

namespace App\Http\Controllers\MiniApp;

use App\Enums\OrderChannel;
use App\Exceptions\Domain\BelowMinOrderAmountException;
use App\Exceptions\Domain\EmptyCartException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Dto\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
    ) {}

    public function show(Request $request, Dealer $dealer): JsonResponse
    {
        $telegramId = $this->telegramId($request);
        $shop = $this->shop($request);

        if ($telegramId === null || $shop === null) {
            return response()->json([
                'items' => [],
                'total' => 0,
                'count' => 0,
                'min_order_amount' => (int) $dealer->min_order_amount,
            ]);
        }

        $cart = $this->cartService->get($telegramId, $shop->id);

        return response()->json($this->cartPayload($cart, $dealer));
    }

    public function add(Request $request, Dealer $dealer): JsonResponse
    {
        $shop = $this->shop($request);
        $telegramId = $this->telegramId($request);

        if ($shop === null || $telegramId === null) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        $request->validate([
            'product_id' => ['required', 'integer'],
            'product_type_id' => ['nullable', 'integer'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'pack_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = Product::query()
            ->forDealer($dealer->id)
            ->active()
            ->visibleInBot()
            ->find($request->integer('product_id'));

        if ($product === null) {
            return response()->json(['message' => 'Mahsulot topilmadi'], 404);
        }

        $type = $this->resolveType($product, $request->integer('product_type_id'));

        if ($product->has_types && $type === null) {
            return response()->json(['message' => 'Mahsulot tipi tanlanishi kerak'], 422);
        }

        if (! $product->has_types && $request->integer('product_type_id') > 0) {
            return response()->json(['message' => 'Bu mahsulotda tip yo\'q'], 422);
        }

        if ($type instanceof JsonResponse) {
            return $type;
        }

        $qty = (float) $request->input('qty');
        $packQty = $request->integer('pack_qty');

        if ($error = $this->bulkOnlyError($product, $type, $qty, $packQty)) {
            return response()->json(['message' => $error], 422);
        }

        $cart = $this->cartService->addItem(
            telegramId: $telegramId,
            shopId: $shop->id,
            product: $product,
            qty: $qty,
            packQty: $packQty > 0 ? $packQty : null,
            type: $type,
        );

        return response()->json($this->cartPayload($cart, $dealer));
    }

    public function update(Request $request, Dealer $dealer, int $productId): JsonResponse
    {
        $shop = $this->shop($request);
        $telegramId = $this->telegramId($request);

        if ($shop === null || $telegramId === null) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        $request->validate([
            'qty' => ['required', 'numeric', 'min:0'],
            'pack_qty' => ['nullable', 'integer', 'min:0'],
            'product_type_id' => ['nullable', 'integer'],
        ]);

        $product = Product::query()
            ->forDealer($dealer->id)
            ->active()
            ->visibleInBot()
            ->find($productId);

        if ($product === null) {
            return response()->json(['message' => 'Mahsulot topilmadi'], 404);
        }

        $type = $this->resolveType($product, $request->integer('product_type_id'));

        if ($type instanceof JsonResponse) {
            return $type;
        }

        $qty = (float) $request->input('qty');
        $packQty = $request->integer('pack_qty');

        if ($qty > 0 && ($error = $this->bulkOnlyError($product, $type, $qty, $packQty))) {
            return response()->json(['message' => $error], 422);
        }

        $cart = $qty <= 0
            ? $this->cartService->removeItem($telegramId, $shop->id, $product->id, $type?->id)
            : $this->cartService->setItemQty(
                telegramId: $telegramId,
                shopId: $shop->id,
                product: $product,
                qty: $qty,
                packQty: $packQty > 0 ? $packQty : null,
                type: $type,
            );

        return response()->json($this->cartPayload($cart, $dealer));
    }

    public function remove(Request $request, Dealer $dealer, int $productId): JsonResponse
    {
        $telegramId = $this->telegramId($request);
        $shop = $this->shop($request);

        if ($telegramId === null || $shop === null) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        $typeId = (int) $request->input('product_type_id', 0);
        $cart = $this->cartService->removeItem($telegramId, $shop->id, $productId, $typeId > 0 ? $typeId : null);

        return response()->json($this->cartPayload($cart, $dealer));
    }

    public function clear(Request $request, Dealer $dealer): JsonResponse
    {
        $telegramId = $this->telegramId($request);
        $shop = $this->shop($request);

        if ($telegramId === null || $shop === null) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        $this->cartService->clear($telegramId, $shop->id);

        return response()->json([
            'items' => [],
            'total' => 0,
            'count' => 0,
            'min_order_amount' => (int) $dealer->min_order_amount,
        ]);
    }

    public function confirm(Request $request, Dealer $dealer): JsonResponse
    {
        $shop = $this->shop($request);
        $member = $this->member($request);
        $telegramId = $this->telegramId($request);

        if ($shop === null || $telegramId === null) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        $cart = $this->cartService->get($telegramId, $shop->id);

        /** @var OrderChannel $channel */
        $channel = $request->attributes->get('order_channel', OrderChannel::BOT);

        try {
            $order = $this->orderService->createFromCart(
                shop: $shop,
                cart: $cart,
                note: $request->input('note'),
                memberId: $member?->id,
                cartOwnerTelegramId: $telegramId,
                channel: $channel,
            );
        } catch (EmptyCartException|ProductUnavailableException|BelowMinOrderAmountException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->displayNumber(),
            'total' => $order->total,
            'status' => $order->status->label(),
            'message' => "Buyurtma #{$order->displayNumber()} qabul qilindi!",
        ], 201);
    }

    private function resolveType(Product $product, int $typeId): ProductType|JsonResponse|null
    {
        if ($typeId <= 0) {
            return null;
        }

        $type = ProductType::query()
            ->where('id', $typeId)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->first();

        if ($type === null) {
            return response()->json(['message' => 'Mahsulot tipi topilmadi'], 404);
        }

        return $type;
    }

    private function shop(Request $request): ?Shop
    {
        return $request->attributes->get('shop');
    }

    private function member(Request $request): ?ShopMember
    {
        return $request->attributes->get('member');
    }

    private function telegramId(Request $request): ?int
    {
        $id = $request->attributes->get('telegram_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, count: int, min_order_amount: int}
     */
    private function cartPayload(Cart $cart, Dealer $dealer): array
    {
        return [
            'items' => $cart->jsonSerialize(),
            'total' => $cart->total(),
            'count' => $cart->count(),
            'min_order_amount' => (int) $dealer->min_order_amount,
        ];
    }

    private function bulkOnlyError(Product $product, ?ProductType $type, float $qty, int $packQty): ?string
    {
        $bulkOnly = (bool) ($type?->bulk_only ?? $product->bulk_only);

        if (! $bulkOnly) {
            return null;
        }

        $packSize = max(1.0, (float) ($type?->pack_size ?? $product->pack_size));

        if ($packQty < 1 || abs($qty - $packQty * $packSize) > 0.0005) {
            $label = $type !== null ? "{$product->name} — {$type->name}" : $product->name;

            return "'{$label}' faqat blokda sotiladi";
        }

        return null;
    }
}
