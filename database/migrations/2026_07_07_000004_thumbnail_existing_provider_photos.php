<?php

use App\Models\StaffPick\ProviderPhoto;
use App\Services\StaffPick\AvatarThumbnailService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Backfill: downsize provider photos uploaded before server-side thumbnailing existed (an
 * 8K phone photo was stored at full ~1.1 MB). New uploads are thumbnailed at upload time;
 * this catches the already-stored originals. Runs after the image is rebuilt with the GD
 * extension, so ImageManager::gd() is available.
 *
 * Only rewrites a photo when the thumbnail is actually smaller — HEIC (which GD can't
 * decode) and already-small images are left untouched. Per-photo failures are logged and
 * skipped rather than failing the deploy. Irreversible: the originals aren't kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(AvatarThumbnailService::class);

        ProviderPhoto::query()
            ->select(['id', 'mime_type'])
            ->get()
            ->each(function (ProviderPhoto $photo) use ($service): void {
                try {
                    $bytes = $photo->readContent();

                    if ($bytes === null) {
                        return;
                    }

                    $processed = $service->process($bytes, $photo->mime_type);

                    if (strlen($processed['bytes']) < strlen($bytes)) {
                        $photo->storeContent($processed['bytes']);
                        $photo->update([
                            'mime_type' => $processed['mime'],
                            'file_size' => strlen($processed['bytes']),
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::warning('Provider photo thumbnail backfill skipped a row.', [
                        'provider_photo_id' => $photo->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible — original full-resolution images are not retained.
    }
};
