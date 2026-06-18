<?php

namespace App\Services\StaffPick\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Contract for a pluggable tenant SSO provider (Google Workspace, Okta, Azure AD,
 * SAML…). A concrete provider is resolved per tenant by {@see SsoService}.
 */
interface SsoProviderInterface
{
    /**
     * The provider authorization URL to send the user to.
     */
    public function getRedirectUrl(Tenant $tenant): string;

    /**
     * Handle the provider's callback: verify the identity, validate the email domain,
     * find or create the user, attach them to the tenant, and return the user.
     */
    public function handleCallback(Tenant $tenant, Request $request): User;

    /**
     * Whether the given email is allowed to SSO into this tenant (its domain must
     * match the tenant's configured SSO domain).
     */
    public function validateEmailDomain(string $email, Tenant $tenant): bool;
}
