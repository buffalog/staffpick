<?php

namespace App\Http\Controllers;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\ProviderApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StaffPick\ProviderApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Download a credential file an applicant uploaded. Authenticated + restricted to
     * admins/staff of the application's tenant (PHI-adjacent documents).
     */
    public function downloadCredential(ProviderApplication $application, int $index): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canReview($user, $application), 403);

        $upload = ($application->credential_uploads ?? [])[$index] ?? null;
        abort_if($upload === null || blank($upload['path'] ?? null) || ! Storage::exists($upload['path']), 404);

        return Storage::download($upload['path'], $upload['original_name'] ?? basename($upload['path']));
    }

    private function canReview(User $user, ProviderApplication $application): bool
    {
        return $user->is_super_admin
            || $user->hasSpRole($application->tenant_id, TenancyPermissionConstants::ROLE_SP_ADMIN)
            || $user->hasSpRole($application->tenant_id, TenancyPermissionConstants::ROLE_SP_STAFF);
    }

    private function resolveTenant(string $tenantSlug): Tenant
    {
        return Tenant::query()->where('uuid', $tenantSlug)->firstOrFail();
    }
}
