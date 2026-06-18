<?php

namespace App\Filament\Dashboard\Auth;

use App\Models\Tenant;
use Filament\Auth\Pages\Login;

/**
 * The dashboard panel's login page. It is the standard Filament email/password login
 * (so that form — the super-admin escape hatch — always renders and works), with SSO
 * and social buttons injected above it via a render hook. The intended tenant is read
 * from the redirect the user was sent here from, so the SSO button can be tenant-aware.
 */
class TenantLogin extends Login
{
    /**
     * The tenant the user was heading to before being redirected to login (parsed from
     * the intended URL, e.g. /dashboard/{uuid}/board), or null.
     */
    public static function intendedTenant(): ?Tenant
    {
        $intended = (string) session('url.intended', '');

        if (! preg_match('~/dashboard/([^/?#]+)~', $intended, $matches)) {
            return null;
        }

        // 'login' is the panel's own login path, not a tenant slug.
        if ($matches[1] === 'login') {
            return null;
        }

        return Tenant::query()->where('uuid', $matches[1])->first();
    }
}
