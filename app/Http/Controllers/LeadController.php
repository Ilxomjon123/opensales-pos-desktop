<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;

final class LeadController extends Controller
{
    public function store(StoreLeadRequest $request): RedirectResponse
    {
        Lead::create([
            ...$request->validated(),
            'status' => LeadStatus::NEW,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Zayavkangiz qabul qilindi. Tez orada bog'lanamiz.",
        ]);
    }
}
