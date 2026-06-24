<x-layouts.intake>
    <div class="mx-auto max-w-3xl px-4 py-8">
        <header class="mb-6 flex items-center gap-3">
            @if ($tenant->config?->logo_url ?? false)
                <img src="{{ $tenant->config->logo_url }}" alt="{{ $tenant->name }}" class="h-10 w-auto">
            @endif
            <div>
                <h1 class="text-xl font-semibold text-gray-900">{{ __('Register with :tenant', ['tenant' => $tenant->name]) }}</h1>
                <p class="text-sm text-gray-500">{{ __('Referral source registration') }}</p>
            </div>
        </header>

        @livewire(\App\Livewire\StaffPick\PublicReferralSourceForm::class, [
            'tenantId' => $tenant->id,
            'tenantName' => $tenant->name,
        ])

        <footer class="mt-8 text-center text-xs text-gray-400">
            {{ __('Powered by StaffPick') }}
        </footer>
    </div>
</x-layouts.intake>
