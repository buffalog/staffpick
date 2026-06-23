<?php

namespace App\Http\Controllers;

use App\Models\StaffPick\ProviderApplication;
use App\Models\Tenant;
use App\Services\StaffPick\ProviderApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public entry points for the provider self-serve onboarding wizard. No auth: the
 * tenant is resolved from its slug (uuid) and the in-progress application from its
 * resume token. The wizard interactivity itself lives in the Livewire component.
 */
class ProviderApplicationController extends Controller
{
    public function __construct(
        private ProviderApplicationService $applications,
    ) {}

    /**
     * First load: create a fresh draft for the tenant and redirect to its tokenized
     * resume URL (so a refresh doesn't spawn another draft).
     */
    public function show(string $tenantSlug): RedirectResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $application = $this->applications->createDraft($tenant);

        return redirect()->route('staffpick.application.resume', [
            'tenantSlug' => $tenantSlug,
            'token' => $application->application_token,
        ]);
    }

    /**
     * Resume (or continue) an application at its current step.
     */
    public function resume(string $tenantSlug, string $token): View
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $application = ProviderApplication::query()
            ->where('tenant_id', $tenant->id)
            ->where('application_token', $token)
            ->firstOrFail();

        return view('staffpick.provider-application', [
            'tenant' => $tenant,
            'application' => $application,
        ]);
    }

    private function resolveTenant(string $tenantSlug): Tenant
    {
        return Tenant::query()->where('uuid', $tenantSlug)->firstOrFail();
    }
}
