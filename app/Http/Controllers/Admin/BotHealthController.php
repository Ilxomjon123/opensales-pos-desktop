<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Services\AuditLogger;
use App\Services\BotHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class BotHealthController extends Controller
{
    public function __construct(
        private readonly BotHealthService $health,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        $dealers = Dealer::query()
            ->orderByRaw('CASE WHEN webhook_last_error_message IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('webhook_checked_at')
            ->get()
            ->map(fn (Dealer $d): array => $this->snapshot($d))
            ->values()
            ->all();

        return Inertia::render('Admin/BotHealth/Index', [
            'dealers' => $dealers,
            'summary' => $this->summary($dealers),
        ]);
    }

    public function refresh(Dealer $dealer): RedirectResponse
    {
        $this->health->check($dealer);

        $this->audit->log('bot_health.refresh', $dealer);

        return back()->with('status', "\"{$dealer->name}\" boti tekshirildi");
    }

    public function refreshAll(): RedirectResponse
    {
        $result = $this->health->checkAll();

        $this->audit->log('bot_health.refresh_all', null, $result);

        return back()->with('status', sprintf(
            'Tekshirildi: %d — OK: %d, xato: %d',
            $result['checked'],
            $result['ok'],
            $result['failed'],
        ));
    }

    /**
     * @return array{
     *     id: int, name: string, is_active: bool, webhook_set_at: string|null,
     *     webhook_checked_at: string|null, webhook_url: string|null, webhook_pending_updates: int,
     *     webhook_last_error_message: string|null, webhook_last_error_at: string|null,
     *     error_age_minutes: int|null, health: string
     * }
     */
    private function snapshot(Dealer $d): array
    {
        $errorAge = $d->webhook_last_error_at !== null
            ? (int) $d->webhook_last_error_at->diffInMinutes(CarbonImmutable::now())
            : null;

        return [
            'id' => $d->id,
            'name' => $d->name,
            'is_active' => (bool) $d->is_active,
            'webhook_set_at' => $d->webhook_set_at?->toIso8601String(),
            'webhook_checked_at' => $d->webhook_checked_at?->toIso8601String(),
            'webhook_url' => $d->webhook_url,
            'webhook_pending_updates' => (int) ($d->webhook_pending_updates ?? 0),
            'webhook_last_error_message' => $d->webhook_last_error_message,
            'webhook_last_error_at' => $d->webhook_last_error_at?->toIso8601String(),
            'error_age_minutes' => $errorAge,
            'health' => $this->healthFor($d, $errorAge),
        ];
    }

    private function healthFor(Dealer $d, ?int $errorAgeMinutes): string
    {
        if (! $d->is_active) {
            return 'disabled';
        }

        if ($d->webhook_set_at === null) {
            return 'no_webhook';
        }

        if ($errorAgeMinutes !== null && $errorAgeMinutes < 60) {
            return 'error';
        }

        if (($d->webhook_pending_updates ?? 0) > 100) {
            return 'backed_up';
        }

        if ($d->webhook_checked_at === null) {
            return 'unknown';
        }

        return 'healthy';
    }

    /**
     * @param  list<array<string, mixed>>  $dealers
     * @return array{total: int, healthy: int, error: int, no_webhook: int, disabled: int}
     */
    private function summary(array $dealers): array
    {
        $counts = array_fill_keys(['healthy', 'error', 'backed_up', 'no_webhook', 'unknown', 'disabled'], 0);

        foreach ($dealers as $d) {
            $counts[(string) $d['health']] = ($counts[(string) $d['health']] ?? 0) + 1;
        }

        return [
            'total' => count($dealers),
            'healthy' => $counts['healthy'],
            'error' => $counts['error'] + $counts['backed_up'],
            'no_webhook' => $counts['no_webhook'] + $counts['unknown'],
            'disabled' => $counts['disabled'],
        ];
    }
}
