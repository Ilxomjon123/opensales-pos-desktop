<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Dealer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DealerRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Dealer $dealer) {}
}
