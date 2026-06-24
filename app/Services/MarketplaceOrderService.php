<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\RecordStockTransactionAction;
use App\Enums\CommissionType;
use App\Enums\Currency;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\ProductVisibility;
use App\Enums\TransactionType;
use App\Events\MarketplaceOrderCreated;
use App\Exceptions\Domain\BelowMinOrderAmountException;
use App\Exceptions\Domain\EmptyCartException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\InvalidOrderTransitionException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Birja (marketplace) buyurtmalari — diller ↔ diller.
 * Bitta `orders` jadvaliga channel=marketplace bilan yoziladi (unified reporting).
 * Shop OrderService'ga tegmaydi — settlement va stock alohida.
 *
 * Hayot tsikli (shop bilan bir xil statuslar):
 *   pending → assembling (sotuvchi qabul qiladi, stokidan chiqim)
 *   assembling → delivering → delivered → received
 *      (received'da xaridor omboriga kirim + dillerlararo qarz + komissiya)
 *   pending|assembling → cancelled (assembling bo'lsa sotuvchi stoki qaytariladi)
 */
final class MarketplaceOrderService
{
    public function __construct(
        private readonly RecordStockTransactionAction $stockTransaction,
        private readonly MarketplaceFinanceService $finance,
    ) {}

    /**
     * @param  list<array{product_id: int, qty: int|float, pack_qty?: int|null}>  $items
     */
    public function placeOrder(Dealer $buyer, array $items, ?string $note = null): Order
    {
        if ($items === []) {
            throw EmptyCartException::make();
        }

        return DB::transaction(function () use ($buyer, $items, $note): Order {
            $productIds = array_values(array_unique(array_map(
                static fn (array $i): int => (int) $i['product_id'],
                $items,
            )));

            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->visibleInMarketplace()
                ->active()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $seller = $this->resolveSeller($products, $productIds, $buyer);

            $order = Order::query()->create([
                'dealer_id' => $seller->id,
                'buyer_dealer_id' => $buyer->id,
                'currency' => $seller->currency ?? Currency::UZS,
                'channel' => OrderChannel::MARKETPLACE,
                'shop_id' => null,
                'status' => OrderStatus::PENDING,
                'total' => 0,
                'note' => $note,
                'platform_fee_rate' => $this->marketplaceFeeRateSnapshot($seller),
            ]);

            foreach ($items as $row) {
                $product = $products->get((int) $row['product_id']);

                if ($product === null) {
                    throw ProductUnavailableException::forProduct(null, (string) $row['product_id']);
                }

                $qty = (float) $row['qty'];
                $packQty = isset($row['pack_qty']) && (int) $row['pack_qty'] > 0 ? (int) $row['pack_qty'] : null;

                if ($qty <= 0) {
                    throw new InvalidArgumentException('Miqdor musbat bo\'lishi kerak');
                }

                if ((float) $product->stock < $qty) {
                    throw InsufficientStockException::forProduct($product, $qty);
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'pack_price' => $product->pack_price,
                    'unit_cost' => $product->cost_price,
                    'pack_unit_cost' => $product->pack_cost_price,
                    'unit' => $product->unit,
                    'pack_size' => $product->pack_size,
                    'qty' => $qty,
                    'pack_qty' => $packQty,
                ]);
            }

            $order->load('items');
            $total = (int) $order->items->sum(fn (OrderItem $i): int => $i->subtotal());

            // Sotuvchi belgilagan minimal birja buyurtma summasi.
            $minOrderAmount = (int) $seller->marketplace_min_order_amount;
            if ($minOrderAmount > 0 && $total < $minOrderAmount) {
                throw BelowMinOrderAmountException::make($total, $minOrderAmount, $seller->currency ?? Currency::UZS);
            }

            $order->update(['total' => $total]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::PENDING,
                'changed_at' => $order->created_at,
            ]);

            $order->load('buyerDealer', 'dealer');

            event(new MarketplaceOrderCreated($order));

            return $order;
        });
    }

    /**
     * Sotuvchi buyurtmani qabul qiladi — sotuvchi stokidan chiqim.
     */
    public function accept(Order $order): Order
    {
        $this->assertMarketplace($order);
        $this->assertTransition($order, OrderStatus::ASSEMBLING);

        return DB::transaction(function () use ($order): Order {
            $order->load('items');

            foreach ($order->items as $item) {
                /** @var Product|null $product */
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if ($product === null) {
                    throw ProductUnavailableException::forProduct(null, (string) $item->product_name);
                }

                if ((float) $product->stock < (float) $item->qty) {
                    throw InsufficientStockException::forProduct($product, (float) $item->qty);
                }

                $product->decrement('stock', (float) $item->qty);
            }

            return $this->moveTo($order, OrderStatus::ASSEMBLING, ['assembling_at' => now()]);
        });
    }

    public function ship(Order $order): Order
    {
        $this->assertMarketplace($order);

        return $this->moveTo($order, OrderStatus::DELIVERING, ['delivering_at' => now()]);
    }

    public function markDelivered(Order $order): Order
    {
        $this->assertMarketplace($order);

        return $this->moveTo($order, OrderStatus::DELIVERED, ['delivered_at' => now()]);
    }

    /**
     * Xaridor tovarni qabul qildi — o'z omboriga kirim + sotuvchiga qarz + komissiya snapshot.
     */
    public function confirmReceived(Order $order, User $buyerActor): Order
    {
        $this->assertMarketplace($order);
        $this->assertTransition($order, OrderStatus::RECEIVED);

        return DB::transaction(function () use ($order, $buyerActor): Order {
            $order->load('items', 'dealer', 'buyerDealer');

            $lines = [];
            foreach ($order->items as $item) {
                $buyerProduct = $this->matchOrCreateBuyerProduct((int) $order->buyer_dealer_id, $item);
                $lines[] = [
                    'product_id' => $buyerProduct->id,
                    'qty' => (float) $item->qty,
                    'unit_cost' => (float) $item->price,
                    'pack_unit_cost' => $item->pack_price !== null ? (float) $item->pack_price : null,
                ];
            }

            // Xaridor omboriga kirim (supplier'siz — Birja ichki manbasi).
            $this->stockTransaction->execute(
                actor: $buyerActor,
                dealerId: (int) $order->buyer_dealer_id,
                type: TransactionType::STOCK_IN,
                lines: $lines,
                note: 'Birja #'.$order->displayNumber().' — '.$order->dealer->name,
                allowNoSupplier: true,
            );

            // Dillerlararo qarz: xaridor sotuvchiga qarzdor bo'ladi.
            $this->finance->debit(
                seller: $order->dealer,
                buyer: $order->buyerDealer,
                amount: (int) $order->total,
                orderId: $order->id,
                note: 'Birja #'.$order->displayNumber(),
            );

            return $this->moveTo($order, OrderStatus::RECEIVED, [
                'received_at' => now(),
                'delivered_total' => (int) $order->total,
            ]);
        });
    }

    public function cancel(Order $order, ?User $by = null, ?string $reason = null): Order
    {
        $this->assertMarketplace($order);
        $this->assertTransition($order, OrderStatus::CANCELLED);

        return DB::transaction(function () use ($order, $by, $reason): Order {
            // Assembling bo'lsa sotuvchi stoki band qilingan edi — qaytariladi.
            if ($order->status === OrderStatus::ASSEMBLING) {
                $order->load('items');

                foreach ($order->items as $item) {
                    Product::query()
                        ->where('id', $item->product_id)
                        ->increment('stock', (float) $item->qty);
                }
            }

            return $this->moveTo($order, OrderStatus::CANCELLED, [
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $by?->id,
                'cancellation_reason' => $reason,
            ], $by, $reason);
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function moveTo(Order $order, OrderStatus $next, array $extra = [], ?User $by = null, ?string $reason = null): Order
    {
        $previous = $order->status;
        $order->update(['status' => $next, ...$extra]);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $previous,
            'to_status' => $next,
            'changed_by_user_id' => $by?->id,
            'reason' => $reason,
            'changed_at' => now(),
        ]);

        return $order->refresh();
    }

    private function assertMarketplace(Order $order): void
    {
        if ($order->channel !== OrderChannel::MARKETPLACE) {
            throw new InvalidArgumentException('Bu buyurtma Birja kanalida emas');
        }
    }

    private function assertTransition(Order $order, OrderStatus $next): void
    {
        if (! $order->status->canTransitionTo($next)) {
            throw InvalidOrderTransitionException::from($order->status, $next);
        }
    }

    /**
     * Marketplace komissiya stavkasi snapshot (faqat turnover% turida son).
     */
    private function marketplaceFeeRateSnapshot(Dealer $seller): ?float
    {
        $type = $seller->marketplace_commission_type ?? $seller->commission_type ?? CommissionType::TURNOVER_PERCENTAGE;

        if ($type !== CommissionType::TURNOVER_PERCENTAGE) {
            return null;
        }

        return (float) ($seller->marketplace_platform_fee_rate ?? $seller->platform_fee_rate ?? 0);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  list<int>  $requestedIds
     */
    private function resolveSeller($products, array $requestedIds, Dealer $buyer): Dealer
    {
        if ($products->count() !== count($requestedIds)) {
            throw ProductUnavailableException::forProduct(null, 'Tanlangan');
        }

        $sellerIds = $products->pluck('dealer_id')->unique();

        if ($sellerIds->count() !== 1) {
            throw new InvalidArgumentException('Bitta buyurtmada faqat bitta sotuvchi mahsulotlari bo\'lishi mumkin');
        }

        $sellerId = (int) $sellerIds->first();

        if ($sellerId === $buyer->id) {
            throw new InvalidArgumentException('O\'z mahsulotingizni Birja orqali sotib ololmaysiz');
        }

        return Dealer::query()->findOrFail($sellerId);
    }

    /**
     * Xaridor katalogida nom bo'yicha mos mahsulot topadi yoki yangi (nofaol) yaratadi.
     */
    private function matchOrCreateBuyerProduct(int $buyerDealerId, OrderItem $item): Product
    {
        $existing = Product::query()
            ->forDealer($buyerDealerId)
            ->whereRaw('LOWER(name) = LOWER(?)', [$item->product_name])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Product::query()->create([
            'dealer_id' => $buyerDealerId,
            'name' => $item->product_name,
            'price' => $item->price,
            'pack_price' => $item->pack_price,
            'cost_price' => $item->price,
            'pack_cost_price' => $item->pack_price,
            'stock' => 0,
            'pack_size' => $item->pack_size,
            'unit' => $item->unit,
            'is_active' => false,
            'visibility' => ProductVisibility::BOT_ONLY,
        ]);
    }
}
