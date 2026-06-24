<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compare;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class CompareController extends Controller
{
    public function opensalesVs1c(): Response
    {
        return Inertia::render('Compare/OpenSalesVs1c');
    }

    public function opensalesVsSalesDoctor(): Response
    {
        return Inertia::render('Compare/OpenSalesVsSalesDoctor');
    }
}
