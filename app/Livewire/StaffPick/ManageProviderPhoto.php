<?php

namespace App\Livewire\StaffPick;

use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderPhoto;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Upload / replace a provider's profile photo (BLOB in Azure SQL). Reused on the staff
 * provider view and the provider's own "My Provider Profile" self-service page. One row per
 * provider, replace-in-place; there is no history and no required-photo constraint.
 *
 * Extension -> MIME is resolved from a fixed map so the stored type is correct for inline
 * rendering (heic content-guessing is unreliable).
 */
class ManageProviderPhoto extends Component
{
    use WithFileUploads;

    /** @var array<string, string> */
    private const MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'heic' => 'image/heic',
    ];

    #[Locked]
    public int $providerId;

    public $upload;

    public function mount(int $providerId): void
    {
        $this->providerId = $providerId;

        abort_unless($this->provider->isPhotoAccessibleBy(auth()->user()), 403);
    }

    #[Computed]
    public function provider(): Provider
    {
        return Provider::with('discipline')->findOrFail($this->providerId);
    }

    // Named save(), NOT upload(): a method named upload() would collide with the public
    // $upload file property (wire:model target), and Livewire resolves the name to the
    // property, so wire:click never invokes the action. Tests can't catch this — they call
    // the method directly server-side — so it must stay verified in a real browser.
    public function save(): void
    {
        $this->validate([
            'upload' => [
                'required',
                'file',
                'extensions:'.implode(',', ProviderPhoto::ACCEPTED_EXTENSIONS),
                'max:'.ProviderPhoto::MAX_SIZE_KB,
            ],
        ], attributes: ['upload' => __('photo')]);

        abort_unless($this->provider->isPhotoAccessibleBy(auth()->user()), 403);

        $extension = strtolower($this->upload->getClientOriginalExtension());

        // Replace-in-place: one row per provider. updateOrCreate bumps updated_at, which
        // busts the versioned photo URL so the new image shows immediately.
        $photo = ProviderPhoto::updateOrCreate(
            ['provider_id' => $this->provider->id],
            [
                'mime_type' => self::MIME_BY_EXTENSION[$extension] ?? 'application/octet-stream',
                'file_size' => $this->upload->getSize(),
                'updated_by_user_id' => auth()->id(),
            ],
        );
        $photo->storeContent($this->upload->get());

        $this->reset('upload');
        unset($this->provider);
    }

    public function render(): View
    {
        return view('livewire.staffpick.manage-provider-photo');
    }
}
