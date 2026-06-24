<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class PricingController extends Controller
{
    public function calculator(): Response
    {
        return Inertia::render('Pricing/Calculator');
    }
}
