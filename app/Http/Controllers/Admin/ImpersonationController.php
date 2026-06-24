<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Services\AuditLogger;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ImpersonationController extends Controller
{
    public function __construct(
        private readonly ImpersonationService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function start(Request $request, Dealer $dealer): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor?->isSuperAdmin(), 403);

        $target = $dealer->users()->where('role', UserRole::DEALER)->first();

        if ($target === null) {
            return back()->with('error', 'Diller egasi hisobi topilmadi');
        }

        $this->audit->log('impersonate.start', $dealer, [
            'actor_id' => $actor->id,
            'target_user_id' => $target->id,
        ]);

        $this->service->start($actor, $target);

        return redirect()->route('dealer.orders.index')
            ->with('status', "\"{$dealer->name}\" hisobi sifatida kirdingiz");
    }

    public function stop(Request $request): RedirectResponse
    {
        $impersonatedUserId = $request->user()?->id;

        $original = $this->service->stop();

        $this->audit->log('impersonate.stop', null, [
            'impersonated_user_id' => $impersonatedUserId,
            'restored_user_id' => $original?->id,
        ]);

        return redirect()->route('admin.dealers.index')
            ->with('status', 'Impersonation tugatildi');
    }
}
