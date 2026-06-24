<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\PlatformPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformPaymentController extends Controller
{
    private const PER_PAGE = 30;

    /** @var array<int, string> */
    private const SORTABLE = ['created_at', 'amount', 'dealer_name'];

    public function index(Request $request): Response
    {
        $sort = in_array($request->string('sort')->value(), self::SORTABLE, true)
            ? $request->string('sort')->value()
            : 'created_at';

        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';

        $query = PlatformPayment::query()
            ->select('platform_payments.*')
            ->with('dealer:id,name')
            ->when(
                $request->filled('dealer_id'),
                fn ($q) => $q->where('platform_payments.dealer_id', $request->integer('dealer_id')),
            )
            ->when(
                $request->filled('date_from'),
                fn ($q) => $q->whereDate('platform_payments.created_at', '>=', $request->string('date_from')),
            )
            ->when(
                $request->filled('date_to'),
                fn ($q) => $q->whereDate('platform_payments.created_at', '<=', $request->string('date_to')),
            );

        if ($sort === 'dealer_name') {
            $query->leftJoin('dealers', 'dealers.id', '=', 'platform_payments.dealer_id')
                ->orderBy('dealers.name', $direction)
                ->orderBy('platform_payments.id', 'desc');
        } else {
            $column = "platform_payments.{$sort}";
            $query->orderBy($column, $direction)->orderBy('platform_payments.id', 'desc');
        }

        $payments = $query->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $this->transform($payments),
            'dealers' => Dealer::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Dealer $d): array => ['id' => $d->id, 'name' => $d->name])
                ->all(),
            'totals' => $this->totals($request),
            'filters' => [
                'dealer_id' => $request->filled('dealer_id') ? $request->integer('dealer_id') : null,
                'date_from' => $request->string('date_from')->value() ?: null,
                'date_to' => $request->string('date_to')->value() ?: null,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * @return array{
     *     data: list<array{id: int, dealer_id: int, dealer_name: string, amount: int, discount: int, note: string|null, created_at: string|null}>,
     *     meta: array{total: int, last_page: int, current_page: int, per_page: int},
     *     links: array{prev: string|null, next: string|null}
     * }
     */
    private function transform(LengthAwarePaginator $page): array
    {
        return [
            'data' => collect($page->items())
                ->map(fn (PlatformPayment $p): array => [
                    'id' => $p->id,
                    'dealer_id' => $p->dealer_id,
                    'dealer_name' => $p->dealer?->name ?? '—',
                    'amount' => (int) $p->amount,
                    'discount' => (int) $p->discount,
                    'note' => $p->note,
                    'created_at' => $p->created_at?->toIso8601String(),
                ])
                ->all(),
            'meta' => [
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ],
            'links' => [
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
        ];
    }

    /**
     * Filter qo'llanilgan holatdagi jami yig'indi va sanoq.
     *
     * @return array{count: int, sum: int, discount: int}
     */
    private function totals(Request $request): array
    {
        $q = PlatformPayment::query()
            ->when(
                $request->filled('dealer_id'),
                fn ($q) => $q->where('dealer_id', $request->integer('dealer_id')),
            )
            ->when(
                $request->filled('date_from'),
                fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')),
            )
            ->when(
                $request->filled('date_to'),
                fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')),
            );

        return [
            'count' => (int) (clone $q)->count(),
            'sum' => (int) (clone $q)->sum('amount'),
            'discount' => (int) (clone $q)->sum('discount'),
        ];
    }
}
