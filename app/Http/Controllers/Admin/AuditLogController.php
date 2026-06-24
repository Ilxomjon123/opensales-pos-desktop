<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        $logs = AuditLog::query()
            ->with('user:id,name,username')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $this->transform($logs),
            'actions' => $actions,
            'filters' => $request->only(['action', 'user_id', 'date_from', 'date_to']),
        ]);
    }

    /**
     * @return array{
     *     data: list<array{id: int, action: string, actor: array{id?: int, name: string, username?: string}, subject_type: string|null, subject_id: int|null, changes: array<string, mixed>|null, ip: string|null, created_at: string|null}>,
     *     meta: array{total: int, last_page: int, current_page: int, per_page: int},
     *     links: array{prev: string|null, next: string|null}
     * }
     */
    private function transform(LengthAwarePaginator $page): array
    {
        return [
            'data' => collect($page->items())
                ->map(fn (AuditLog $l): array => [
                    'id' => $l->id,
                    'action' => $l->action,
                    'actor' => $l->user !== null
                        ? ['id' => $l->user->id, 'name' => $l->user->name, 'username' => $l->user->username]
                        : ['name' => $l->actor_name ?? 'Tizim'],
                    'subject_type' => $l->subject_type !== null ? class_basename($l->subject_type) : null,
                    'subject_id' => $l->subject_id,
                    'changes' => $l->changes,
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
