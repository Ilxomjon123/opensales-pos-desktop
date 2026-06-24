<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Actions\RecordStockTransactionAction;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreStockTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class StockTransactionController extends Controller
{
    public function __construct(
        private readonly RecordStockTransactionAction $recordTransaction,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $dealerId = (int) $request->user()->dealer_id;
        $type = TransactionType::tryFrom((string) $request->query('type', TransactionType::STOCK_IN->value))
            ?? TransactionType::STOCK_IN;

        $transactions = Transaction::query()
            ->forDealer($dealerId)
            ->ofType($type)
            ->with([
                'supplier:id,name',
                'shop:id,name',
                'order:id,number',
                'details:id,transaction_id,product_id,product_type_id,order_item_id,product_name,product_type_name,qty,pack_qty,unit_cost,pack_unit_cost,stock_before,stock_after,disposition',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $suppliers = Supplier::query()
            ->forDealer($dealerId)
            ->active()
            ->orderBy('name')
            ->get($type === TransactionType::SUPPLIER_RETURN ? ['id', 'name', 'balance'] : ['id', 'name']);

        $shops = $type === TransactionType::SHOP_RETURN
            ? Shop::query()
                ->forDealer($dealerId)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'balance'])
            : collect();

        $products = $type === TransactionType::SUPPLIER_RETURN || $type === TransactionType::SHOP_RETURN
            ? $this->returnableProducts($dealerId, $type === TransactionType::SHOP_RETURN)
            : collect();

        return Inertia::render('Dealer/Products/StockHistory', [
            'transactions' => TransactionResource::collection($transactions),
            'suppliers' => $suppliers,
            'shops' => $shops,
            'products' => $products,
            'filters' => ['type' => $type->value],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function returnableProducts(int $dealerId, bool $includeZeroStock = false): Collection
    {
        $query = Product::query()
            ->forDealer($dealerId)
            ->where('is_active', true)
            ->with(['types' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->limit(500);

        if (! $includeZeroStock) {
            $query->where('stock', '>', 0)
                ->with(['types' => fn ($q) => $q->where('stock', '>', 0)->orderBy('name')]);
        }

        return $query
            ->get(['id', 'name', 'unit', 'stock', 'has_types', 'price', 'pack_price', 'pack_size', 'bulk_only'])
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'unit' => $p->unit->value,
                'stock' => (float) $p->stock,
                'has_types' => (bool) $p->has_types,
                'price' => (float) $p->price,
                'pack_price' => $p->pack_price !== null ? (float) $p->pack_price : null,
                'pack_size' => (float) $p->pack_size,
                'bulk_only' => (bool) $p->bulk_only,
                'types' => $p->types->map(fn (ProductType $t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'stock' => (float) $t->stock,
                    'price' => (float) ($t->price ?? $p->price),
                    'pack_price' => $t->pack_price !== null ? (float) $t->pack_price : null,
                    'pack_size' => (float) ($t->pack_size ?? $p->pack_size),
                    'bulk_only' => (bool) ($t->bulk_only ?? $p->bulk_only),
                ])->all(),
            ]);
    }

    public function store(StoreStockTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $dealerId = (int) $request->user()->dealer_id;
        $data = $request->validated();

        $paidAmount = (int) ($data['paid_amount'] ?? 0);

        $this->recordTransaction->execute(
            actor: $request->user(),
            dealerId: $dealerId,
            type: TransactionType::STOCK_IN,
            lines: $data['items'],
            note: $data['note'] ?? null,
            supplierId: (int) $data['supplier_id'],
            paidAmount: $paidAmount,
            paymentMethod: $request->paymentMethod(),
            cardholderName: $paidAmount > 0 ? ($data['cardholder_name'] ?? null) : null,
        );

        $itemsCount = count($data['items']);

        return back()->with(
            'status',
            $itemsCount === 1
                ? 'Prixod yozildi'
                : "Prixod yozildi: {$itemsCount} ta mahsulot",
        );
    }
}
