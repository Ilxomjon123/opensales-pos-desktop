<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLeadStatusRequest;
use App\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $leads = Lead::query()
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status'))
            )
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($qq) use ($term): void {
                    $qq->where('name', 'ilike', $term)
                        ->orWhere('phone', 'ilike', $term)
                        ->orWhere('company', 'ilike', $term);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Leads/Index', [
            'leads' => $this->transform($leads),
            'filters' => $request->only(['status', 'search']),
            'statuses' => collect(LeadStatus::cases())
                ->map(fn (LeadStatus $s): array => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ])
                ->all(),
            'totals' => [
                'all' => Lead::query()->count(),
                'new' => Lead::query()->new()->count(),
            ],
        ]);
    }

    public function update(UpdateLeadStatusRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update([
            'status' => LeadStatus::from($request->validated('status')),
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Holat yangilandi',
        ]);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Zayavka o'chirildi",
        ]);
    }

    /**
     * @return array{
     *     data: list<array{id: int, name: string, phone: string, company: string|null, message: string|null, status: string, status_label: string, ip: string|null, created_at: string|null}>,
     *     meta: array{total: int, last_page: int, current_page: int, per_page: int},
     *     links: array{prev: string|null, next: string|null}
     * }
     */
    private function transform(LengthAwarePaginator $page): array
    {
        return [
            'data' => collect($page->items())
                ->map(fn (Lead $l): array => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'phone' => $l->phone,
                    'company' => $l->company,
                    'message' => $l->message,
                    'status' => $l->status->value,
                    'status_label' => $l->status->label(),
                    'ip' => $l->ip,
                    'created_at' => $l->created_at?->toIso8601String(),
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
}
