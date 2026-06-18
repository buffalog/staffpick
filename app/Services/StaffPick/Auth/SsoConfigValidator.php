<?php

namespace App\Services\StaffPick\Auth;

use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use Throwable;

/**
 * Validates an entered (unsaved) SSO configuration without persisting it. A real
 * OAuth round-trip needs user consent, so this only confirms the config is
 * structurally usable: a supported provider, all required fields present, and a
 * redirect URL that builds. Extracted from the settings Page so it is testable.
 */
class SsoConfigValidator
{
    public function __construct(private SsoService $sso) {}

    /**
     * @param  array<string, mixed>  $state  the entered (unsaved) form state
     */
    public function check(Tenant $tenant, TenantConfig $existing, array $state): SsoConfigResult
    {
        $missing = [];

        foreach (['sso_provider' => __('provider'), 'sso_client_id' => __('client ID'), 'sso_domain' => __('domain')] as $key => $label) {
            if (blank($state[$key] ?? null)) {
                $missing[] = $label;
            }
        }

        // The secret may already be saved (left blank in the form to keep it).
        if (blank($state['sso_client_secret'] ?? null) && blank($existing->sso_client_secret)) {
            $missing[] = __('client secret');
        }

        if ($missing !== []) {
            return SsoConfigResult::error(
                __('Incomplete configuration'),
                __('Missing: :fields', ['fields' => implode(', ', $missing)]),
            );
        }

        $probe = new TenantConfig(array_merge($existing->getAttributes(), [
            'sso_provider' => $state['sso_provider'],
            'sso_client_id' => $state['sso_client_id'],
            'sso_domain' => $state['sso_domain'],
            'sso_enabled' => true,
        ]));

        if (filled($state['sso_client_secret'] ?? null)) {
            $probe->sso_client_secret = $state['sso_client_secret'];
        }

        $provider = $this->sso->providerFor($tenant, $probe);

        if ($provider === null) {
            return SsoConfigResult::warning(
                __('Provider not yet available'),
                __('Only Google Workspace is implemented so far.'),
            );
        }

        try {
            // Confirms the redirect URL builds — validates structural usability, not
            // the live credentials (which need a real sign-in to verify).
            $provider->getRedirectUrl();
        } catch (Throwable $e) {
            return SsoConfigResult::error(__('Configuration error'), $e->getMessage());
        }

        return SsoConfigResult::success(
            __('Configuration looks valid'),
            __('The redirect URL builds correctly. Complete a real sign-in to fully verify the credentials.'),
        );
    }
}
