<?php

namespace App\Services\StaffPick\Auth;

use App\Models\User;

/**
 * Contract for a pluggable tenant SSO provider (Google Workspace, Okta, Azure AD,
 * SAML…). A concrete provider is resolved per tenant by {@see SsoService}, which
 * constructs it with its tenant + config, so the methods need no tenant argument.
 */
interface SsoProviderInterface
{
    /**
     * The provider authorization URL to send the user to.
     */
    public function getRedirectUrl(): string;

    /**
     * Handle the provider's callback: verify the identity, validate the email domain,
     * find or create the user, attach them to the tenant, and return the user.
     */
    public function handleCallback(): User;

    /**
     * Whether the given email is allowed to SSO into this provider's tenant (its
     * domain must match the tenant's configured SSO domain).
     */
    public function validateEmailDomain(string $email): bool;
}
