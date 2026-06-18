<?php

namespace App\Services\StaffPick\Auth;

use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Services\TenantPermissionService;

/**
 * Resolves the concrete SSO provider for a tenant based on its stored configuration.
 * Returns null when SSO is not enabled/configured (or the provider isn't implemented
 * yet), so callers can cleanly fall back to email/password.
 */
class SsoService
{
    public function getSsoProvider(Tenant $tenant): ?SsoProviderInterface
    {
        $config = TenantConfig::query()->where('tenant_id', $tenant->getKey())->first();

        if (! $config instanceof TenantConfig || ! $config->ssoEnabled()) {
            return null;
        }

        return $this->providerFor($tenant, $config);
    }

    /**
     * Build the concrete provider for a (possibly unsaved) config, or null when the
     * named provider isn't implemented yet. Single source of truth for which SSO
     * providers exist — used both for live sign-in and for the settings "test"
     * probe, so adding Okta/Azure later only touches this match.
     */
    public function providerFor(Tenant $tenant, TenantConfig $config): ?SsoProviderInterface
    {
        return match ($config->sso_provider) {
            'google_workspace' => new GoogleWorkspaceSsoProvider($tenant, $config, app(TenantPermissionService::class)),
            // okta, azure_ad, saml — interface is ready; concrete drivers pending.
            default => null,
        };
    }
}
