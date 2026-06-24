<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;

/**
 * Super admin boshqa foydalanuvchi sifatida kirishi.
 * Original user ID session ga saqlanadi; banner va stop uchun ishlatiladi.
 *
 * XAVFSIZLIK:
 *   - Faqat super admin start qila oladi — controller tekshiradi
 *   - Impersonation paytida faqat stop ishlaydi, start yana ishlamaydi
 */
final class ImpersonationService
{
    private const SESSION_KEY = 'impersonator_id';

    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly Session $session,
    ) {}

    public function start(User $actor, User $target): void
    {
        if ($this->isImpersonating()) {
            return;
        }

        $this->session->put(self::SESSION_KEY, $actor->id);

        $this->guard->logout();
        $this->guard->login($target);
    }

    public function stop(): ?User
    {
        $originalId = $this->session->pull(self::SESSION_KEY);

        if ($originalId === null) {
            return null;
        }

        $this->guard->logout();
        $this->guard->loginUsingId((int) $originalId);

        return $this->guard->user();
    }

    public function isImpersonating(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    public function impersonatorId(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return $id !== null ? (int) $id : null;
    }
}
