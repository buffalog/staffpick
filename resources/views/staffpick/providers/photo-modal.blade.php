<div>
    @livewire(
        \App\Livewire\StaffPick\ManageProviderPhoto::class,
        ['providerId' => $providerId],
        key('provider-photo-'.$providerId)
    )
</div>
