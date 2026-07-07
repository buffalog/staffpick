<div>
    @livewire(
        \App\Livewire\StaffPick\ManageCredentialAttachments::class,
        ['credentialId' => $credentialId],
        key('cred-attach-'.$credentialId)
    )
</div>
