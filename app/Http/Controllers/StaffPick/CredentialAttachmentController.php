<?php

namespace App\Http\Controllers\StaffPick;

use App\Http\Controllers\Controller;
use App\Models\StaffPick\CredentialAttachment;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Streams credential attachment BLOBs out of Azure SQL to authenticated users. Runs outside
 * any Filament panel, so authorization is computed from the record's own tenant via
 * ProviderCredential::isAccessibleBy — the same visible_to_scheduler gate that governs
 * seeing, uploading, and verifying the parent credential.
 *
 * Soft-deleted attachments are excluded by implicit route-model binding (the model uses
 * SoftDeletes), so a tombstoned document 404s rather than serving a cleared BLOB.
 */
class CredentialAttachmentController extends Controller
{
    /** Inline: images render in an <img>, PDFs in the browser's native viewer, DOC/DOCX hand off to the OS. */
    public function view(CredentialAttachment $attachment): Response
    {
        return $this->stream($attachment, HeaderUtils::DISPOSITION_INLINE);
    }

    /** Force a download regardless of file type. */
    public function download(CredentialAttachment $attachment): Response
    {
        return $this->stream($attachment, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    private function stream(CredentialAttachment $attachment, string $disposition): Response
    {
        abort_unless($attachment->providerCredential?->isAccessibleBy(auth()->user()) ?? false, 403);

        // Content is cleared on soft delete; a live row with no bytes has nothing to serve.
        $bytes = $attachment->readContent();
        abort_if($bytes === null, 404);

        $filename = HeaderUtils::makeDisposition(
            $disposition,
            $attachment->original_filename,
            // ASCII fallback for the filename* header.
            preg_replace('/[^\x20-\x7e]/', '_', $attachment->original_filename),
        );

        return response($bytes, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => $filename,
            // Never let the browser sniff a different, potentially executable, type.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
