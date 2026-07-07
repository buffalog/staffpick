<?php

namespace App\Livewire\StaffPick;

use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\CredentialAttachment;
use App\Models\StaffPick\ProviderCredential;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Attachment manager for a single provider credential, hosted inside the "Attachments"
 * modal on the Credentials relation manager. Lists live (non-tombstoned) attachments newest
 * first, uploads new proof files as BLOBs into Azure SQL, and lets sp_admin / super-admin
 * tombstone a file. View/Download are plain links to the streaming routes.
 *
 * Extension → MIME is resolved from a fixed map (not the temp file's guessed type, which is
 * unreliable for heic/docx) so inline preview serves a correct Content-Type.
 */
class ManageCredentialAttachments extends Component
{
    use WithFileUploads;

    /** @var array<string, string> */
    private const MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'heic' => 'image/heic',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    #[Locked]
    public int $credentialId;

    public $upload;

    public function mount(int $credentialId): void
    {
        $this->credentialId = $credentialId;

        // A user who can't access the parent credential can't reach its attachments at all.
        abort_unless($this->credential->isAccessibleBy(auth()->user()), 403);
    }

    #[Computed]
    public function credential(): ProviderCredential
    {
        return ProviderCredential::with(['provider', 'documentType'])->findOrFail($this->credentialId);
    }

    public function upload(): void
    {
        $this->validate([
            'upload' => [
                'required',
                'file',
                'extensions:'.implode(',', CredentialAttachment::ACCEPTED_EXTENSIONS),
                'max:'.CredentialAttachment::MAX_SIZE_KB,
            ],
        ], attributes: ['upload' => __('file')]);

        abort_unless($this->credential->isAccessibleBy(auth()->user()), 403);

        $extension = strtolower($this->upload->getClientOriginalExtension());

        // Metadata via Eloquent; the BLOB via storeContent() (VARBINARY needs hex, not a
        // plain string bind). Never overwrites an older file — this is always a new row.
        $attachment = $this->credential->attachments()->create([
            'original_filename' => $this->upload->getClientOriginalName(),
            'mime_type' => self::MIME_BY_EXTENSION[$extension] ?? 'application/octet-stream',
            'file_size' => $this->upload->getSize(),
            'uploaded_by_user_id' => auth()->id(),
            'uploaded_at' => now(),
        ]);
        $attachment->storeContent($this->upload->get());

        $this->reset('upload');
        unset($this->credential);
    }

    public function delete(int $attachmentId): void
    {
        // Delete is sp_admin / super-admin only — never sp_hr or sp_staff.
        abort_unless(SpRoleAccess::isAdmin(), 403);

        $attachment = $this->credential->attachments()->whereKey($attachmentId)->firstOrFail();

        // Tombstone: record who removed it, clear the BLOB to reclaim storage, then soft
        // delete — the row + who/when metadata persist as an audit record.
        $attachment->update(['deleted_by_user_id' => auth()->id()]);
        $attachment->clearContent();
        $attachment->delete();
    }

    public function render(): View
    {
        return view('livewire.staffpick.manage-credential-attachments', [
            'attachments' => $this->credential->attachments()
                ->withoutContent()
                ->with('uploadedBy')
                ->orderByDesc('uploaded_at')
                ->get(),
            'canDelete' => SpRoleAccess::isAdmin(),
        ]);
    }
}
