<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Enums\PaymentType;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreSupplierRequest;
use App\Http\Requests\Dealer\UpdateSupplierRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Support\Translit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $dealerId = (int) $request->user()->dealer_id;
        $search = trim($request->string('search')->toString());

        $suppliers = Supplier::query()
            ->forDealer($dealerId)
            ->when($search !== '', fn ($q) => Translit::applyLike(
                $q, ['name', 'phone', 'contact_person'], $search
            ))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Dealer/Suppliers/Index', [
            'suppliers' => SupplierResource::collection($suppliers),
            'filters' => ['search' => $search !== '' ? $search : null],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Supplier::class);

        return Inertia::render('Dealer/Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $data = $request->validated();
        $data['dealer_id'] = (int) $request->user()->dealer_id;

        $supplier = Supplier::query()->create($data);

        return redirect()
            ->route('dealer.suppliers.show', $supplier)
            ->with('status', 'Ta\'minotchi yaratildi');
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        $payments = $supplier->payments()
            ->with('transaction:id,type')
            ->latest('id')
            ->limit(50)
            ->get();

        $stockIns = Transaction::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('type', [TransactionType::STOCK_IN, TransactionType::SUPPLIER_RETURN])
            ->with('details')
            ->latest('id')
            ->limit(50)
            ->get();

        $products = Product::query()
            ->forDealer((int) $supplier->dealer_id)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->with(['types' => fn ($q) => $q->where('stock', '>', 0)->orderBy('name')])
            ->orderBy('name')
            ->limit(500)
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

        return Inertia::render('Dealer/Suppliers/Show', [
            'supplier' => SupplierResource::make($supplier),
            'payments' => SupplierPaymentResource::collection($payments),
            'transactions' => TransactionResource::collection($stockIns),
            'products' => $products,
            'paymentTypes' => collect(PaymentType::cases())->map(fn (PaymentType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'canPay' => $request->user()->can('pay', $supplier),
            'canEdit' => $request->user()->can('update', $supplier),
            'canReturn' => $request->user()->can('create', Transaction::class),
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        return Inertia::render('Dealer/Suppliers/Edit', [
            'supplier' => SupplierResource::make($supplier),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        return redirect()
            ->route('dealer.suppliers.show', $supplier)
            ->with('status', 'Ta\'minotchi yangilandi');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->update(['is_active' => false]);

        return redirect()
            ->route('dealer.suppliers.index')
            ->with('status', 'Ta\'minotchi nofaol qilindi');
    }
}
