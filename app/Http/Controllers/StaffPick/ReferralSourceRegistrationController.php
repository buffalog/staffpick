<?php

namespace App\Http\Controllers\StaffPick;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\View\View;

/**
 * Public, no-login self-registration for referral sources. The tenant is resolved
 * from its slug (uuid); the form interactivity lives in the Livewire component.
 */
class ReferralSourceRegistrationController extends Controller
{
    public function show(string $tenantSlug): View
    {
        $tenant = Tenant::query()->where('uuid', $tenantSlug)->firstOrFail();

        return view('staffpick.referral-source-registration', [
            'tenant' => $tenant,
        ]);
    }
}
