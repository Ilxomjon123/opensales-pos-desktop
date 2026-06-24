<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class OnboardingController extends Controller
{
    /**
     * Onboarding checklist'ni yopadi (tugatildi yoki o'tkazib yuborildi).
     */
    public function complete(Request $request): RedirectResponse
    {
        $dealer = Dealer::query()->findOrFail((int) $request->user()->dealer_id);

        if ($dealer->onboarding_completed_at === null) {
            $dealer->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return back();
    }
}
