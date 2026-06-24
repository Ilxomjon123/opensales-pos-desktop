<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Super admin va diller tomonidan bajarilgan sezgir operatsiyalarni yozadi.
 * Controller'lardan to'g'ridan-to'g'ri chaqiriladi — event listener'lardan emas,
 * chunki `changes` payload qaror qabul qilganni eng to'g'ri biladi.
 */
final class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $changes = [],
    ): AuditLog {
        $user = $this->request->user();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'actor_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes !== [] ? $changes : null,
            'ip' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 500),
        ]);
    }
}
