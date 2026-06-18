<?php

namespace App\Services\StaffPick\Auth;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

/**
 * Google Workspace SSO via Laravel Socialite, using the tenant's own OAuth client
 * credentials (stored encrypted in sp_tenant_configs — not the platform Google creds).
 * Socialite handles the OAuth state/CSRF check. The authenticated email's domain must
 * match the tenant's configured sso_domain, so a personal Gmail account cannot sign
 * into a workspace it doesn't belong to.
 */
class GoogleWorkspaceSsoProvider implements SsoProviderInterface
{
    public function __construct(
        private Tenant $tenant,
        private TenantConfig $config,
        private TenantPermissionService $permissions,
    ) {}

    public function getRedirectUrl(Tenant $tenant): string
    {
        return $this->socialite()
            ->with([
                // Domain hint — pre-selects the workspace and limits the account picker.
                'hd' => (string) $this->config->sso_domain,
                'prompt' => 'select_account',
            ])
            ->stateless(false)
            ->redirect()
            ->getTargetUrl();
    }

    public function handleCallback(Tenant $tenant, Request $request): User
    {
        $socialUser = $this->socialite()->user();

        return $this->resolveUser(
            (string) $socialUser->getEmail(),
            $socialUser->getName(),
            $socialUser->getId() !== null ? (string) $socialUser->getId() : null,
        );
    }

    public function validateEmailDomain(string $email, Tenant $tenant): bool
    {
        $domain = strtolower(trim((string) $this->config->sso_domain));

        if ($domain === '') {
            return false;
        }

        return str_ends_with(strtolower(trim($email)), '@'.$domain);
    }

    /**
     * Find-or-create the user for a verified SSO identity, attach them to the tenant,
     * and grant a role. Domain-validated first. Extracted from handleCallback so it is
     * testable without a live OAuth round-trip.
     */
    public function resolveUser(string $email, ?string $name, ?string $googleId): User
    {
        if (! $this->validateEmailDomain($email, $this->tenant)) {
            throw new SsoException("The email domain is not permitted for this workspace ({$this->config->sso_domain}).");
        }

        // The first person to SSO into a brand-new tenant becomes its admin; everyone
        // after gets the standard member role. Decided before we attach this user.
        $isFirstMember = $this->tenant->users()->count() === 0;

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => $name ?: $email,
                'email' => $email,
                // A random local password keeps email/password fallback possible; the
                // user can reset it later. The `password` cast hashes it.
                'password' => Str::password(32),
                'google_id' => $googleId,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
        } elseif (filled($googleId) && blank($user->google_id)) {
            $user->update(['google_id' => $googleId]);
        }

        $this->tenant->users()->syncWithoutDetaching([$user->getKey()]);

        $this->permissions->assignTenantUserRole(
            $this->tenant,
            $user,
            $isFirstMember ? TenancyPermissionConstants::ROLE_ADMIN : TenancyPermissionConstants::ROLE_USER,
        );

        return $user;
    }

    private function socialite(): GoogleProvider
    {
        /** @var GoogleProvider $provider */
        $provider = Socialite::buildProvider(GoogleProvider::class, [
            'client_id' => (string) $this->config->sso_client_id,
            'client_secret' => (string) $this->config->sso_client_secret,
            'redirect' => route('staffpick.sso.callback', ['tenant' => $this->tenant->uuid]),
        ]);

        return $provider;
    }
}
