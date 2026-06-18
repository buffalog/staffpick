@php
    use App\Filament\Dashboard\Auth\TenantLogin;
    use Illuminate\Support\Facades\Route;

    $tenant = TenantLogin::intendedTenant();
    $config = $tenant?->config;
    $ssoEnabled = $config !== null && $config->ssoEnabled();
    $ssoRequired = $config !== null && $config->ssoRequired();
    $ssoUrl = $tenant !== null ? route('staffpick.sso.redirect', ['tenant' => $tenant->uuid]) : null;
    $googleUrl = Route::has('auth.oauth.redirect') ? route('auth.oauth.redirect', ['provider' => 'google']) : null;
    $provider = $config?->sso_provider ? str($config->sso_provider)->headline()->toString() : __('SSO');
@endphp

@if ($ssoEnabled || $googleUrl)
    <div class="flex flex-col gap-2">
        @if ($ssoEnabled && $ssoUrl)
            <a href="{{ $ssoUrl }}"
               class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">
                <x-filament::icon icon="heroicon-o-key" class="h-5 w-5" />
                {{ __('Sign in with :provider', ['provider' => $provider]) }}
            </a>
        @endif

        @if ($googleUrl)
            <a href="{{ $googleUrl }}"
               class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200">
                <x-filament::icon icon="heroicon-o-globe-alt" class="h-5 w-5" />
                {{ __('Sign in with Google') }}
            </a>
        @endif

        <div class="my-2 flex items-center gap-3 text-xs text-gray-400">
            <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
            {{ __('or') }}
            <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
        </div>
    </div>
@endif

@if ($ssoRequired)
    {{--
        SSO is required for staff: hide the email/password form by default, but keep it
        in the DOM and fully functional so the super-admin escape hatch always works.
        Email/password is never blocked server-side — this is purely a UI nudge.
    --}}
    <div
        x-data="{ revealed: false }"
        x-init="$nextTick(() => {
            const form = $root.parentElement?.querySelector('form[wire\\:submit]');
            if (form) {
                form.style.display = 'none';
                window.__spRevealLogin = () => { form.style.display = ''; };
            }
        })"
        class="text-center"
    >
        <button
            type="button"
            x-show="! revealed"
            x-on:click="revealed = true; window.__spRevealLogin && window.__spRevealLogin()"
            class="text-sm text-gray-500 underline transition hover:text-gray-700 dark:text-gray-400"
        >
            {{ __('Super admin? Sign in with email and password') }}
        </button>
    </div>
@endif
