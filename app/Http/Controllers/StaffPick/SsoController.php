<?php

namespace App\Http\Controllers\StaffPick;

use App\Http\Controllers\Controller;
use App\Models\StaffPick\AuthLog;
use App\Models\Tenant;
use App\Services\StaffPick\Auth\AuthLogger;
use App\Services\StaffPick\Auth\SsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Tenant SSO OAuth handshake. Lives outside the Filament panel path so the callback
 * URL is stable and Filament's tenant routing is untouched. The callback is rate
 * limited and every attempt (success and failure) is audited.
 */
class SsoController extends Controller
{
    public function __construct(
        private SsoService $sso,
        private AuthLogger $log,
    ) {}

    public function redirect(string $tenant): RedirectResponse
    {
        $tenantModel = Tenant::query()->where('uuid', $tenant)->firstOrFail();
        $provider = $this->sso->getSsoProvider($tenantModel);

        if ($provider === null) {
            return redirect()->route('login')->withErrors([
                'email' => __('Single sign-on is not enabled for this workspace.'),
            ]);
        }

        $this->log->success(AuthLog::EVENT_SSO_REDIRECT, [
            'tenant_id' => $tenantModel->getKey(),
            'provider' => $tenantModel->config?->sso_provider,
        ]);

        return redirect()->away($provider->getRedirectUrl());
    }

    public function callback(string $tenant): RedirectResponse
    {
        $tenantModel = Tenant::query()->where('uuid', $tenant)->firstOrFail();
        $provider = $this->sso->getSsoProvider($tenantModel);

        if ($provider === null) {
            return redirect()->route('login')->withErrors([
                'email' => __('Single sign-on is not enabled for this workspace.'),
            ]);
        }

        try {
            $user = $provider->handleCallback();
        } catch (Throwable $e) {
            $this->log->failure(AuthLog::EVENT_SSO_CALLBACK, $e->getMessage(), [
                'tenant_id' => $tenantModel->getKey(),
                'provider' => $tenantModel->config?->sso_provider,
            ]);

            // Keep the provider/internal detail in the audit log only; show the
            // unauthenticated user a generic message (no internal error leakage).
            return redirect()->route('login')->withErrors([
                'email' => __('Single sign-on failed. Please try again or contact your administrator.'),
            ]);
        }

        Auth::login($user, remember: true);

        $this->log->success(AuthLog::EVENT_SSO_CALLBACK, [
            'tenant_id' => $tenantModel->getKey(),
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'provider' => $tenantModel->config?->sso_provider,
        ]);

        return redirect()->to('/dashboard/'.$tenantModel->uuid);
    }
}
