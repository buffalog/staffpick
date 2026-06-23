<?php

namespace App\Services\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Mail\StaffPick\ProviderApplicationResumeLink;
use App\Mail\StaffPick\ProviderApplicationSubmittedApplicant;
use App\Mail\StaffPick\ProviderApplicationSubmittedStaff;
use App\Models\StaffPick\ProviderApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Business logic for the public provider onboarding wizard: create/resume drafts,
 * auto-save step data, and submit for review. Shared by the public controller (draft
 * creation) and the Livewire wizard (per-step save + submit).
 */
class ProviderApplicationService
{
    public function __construct(
        private TenantPermissionService $permissions,
    ) {}

    /**
     * Start a fresh draft for a tenant. Identity columns are NOT NULL, so they seed as
     * empty strings until step 1 fills them.
     */
    public function createDraft(Tenant $tenant): ProviderApplication
    {
        return ProviderApplication::create([
            'tenant_id' => $tenant->id,
            'application_token' => Str::random(64),
            'status' => ProviderApplication::STATUS_DRAFT,
            'current_step' => 1,
            'first_name' => '',
            'last_name' => '',
            'email' => '',
        ]);
    }

    /**
     * Persist a wizard step: merge the full wizard state into step_data, copy the
     * step's mapped columns, and advance current_step. After step 1 (identity) is
     * saved with an email, send the applicant their resume link.
     *
     * @param  array<string, mixed>  $columns  mapped DB columns for this step
     * @param  array<string, mixed>  $stepData  full wizard state to persist for resume
     */
    public function saveStep(ProviderApplication $application, int $step, array $columns, array $stepData = []): ProviderApplication
    {
        $application->fill($columns);
        $application->step_data = array_merge($application->step_data ?? [], $stepData);
        $application->current_step = max((int) $application->current_step, $step);
        $application->save();

        if ($step === 1 && filled($application->email)) {
            $this->dispatchSafely('resume link', $application, fn () => Mail::to($application->email)
                ->queue(new ProviderApplicationResumeLink($application)));
        }

        return $application;
    }

    /**
     * Submit the application for staff review and notify both sides.
     */
    public function submit(ProviderApplication $application): ProviderApplication
    {
        $application->update([
            'status' => ProviderApplication::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        if (filled($application->email)) {
            $this->dispatchSafely('applicant confirmation', $application, fn () => Mail::to($application->email)
                ->queue(new ProviderApplicationSubmittedApplicant($application)));
        }

        $staff = $this->staffRecipients($application->tenant_id);

        if ($staff->isNotEmpty()) {
            $this->dispatchSafely('staff notification', $application, fn () => $staff
                ->filter(fn (User $user): bool => filled($user->email))
                ->each(fn (User $user) => Mail::to($user->email)->queue(new ProviderApplicationSubmittedStaff($application))));
        }

        return $application;
    }

    /**
     * Tenant admins to notify (falls back to all tenant users). Mirrors the intake flow.
     *
     * @return Collection<int, User>
     */
    private function staffRecipients(int $tenantId): Collection
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return collect();
        }

        $users = $tenant->users()->get();

        try {
            $admins = $users->filter(fn (User $user): bool => in_array(
                TenancyPermissionConstants::ROLE_ADMIN,
                $this->permissions->getTenantUserRoles($tenant, $user),
                true,
            ));

            if ($admins->isNotEmpty()) {
                return $admins->values();
            }
        } catch (Throwable) {
            // Fall through to notifying all tenant users.
        }

        return $users->values();
    }

    private function dispatchSafely(string $label, ProviderApplication $application, callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::warning("Provider application {$label} failed", [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
