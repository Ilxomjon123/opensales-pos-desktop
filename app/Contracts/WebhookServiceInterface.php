<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Dealer;

interface WebhookServiceInterface
{
    public function register(Dealer $dealer): bool;

    public function remove(Dealer $dealer): bool;

    /** @return array{url: string, pending_update_count: int, last_error_message: string|null, last_error_date: int|null}|null */
    public function getInfo(Dealer $dealer): ?array;

    public function verifyToken(string $token): ?string;

    public function url(Dealer $dealer): string;

    public function setMenuButton(Dealer $dealer): bool;

    public function resetMenuButton(Dealer $dealer): bool;

    public function applyProfile(Dealer $dealer): bool;
}
