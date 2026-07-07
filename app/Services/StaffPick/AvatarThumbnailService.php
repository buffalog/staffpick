<?php

namespace App\Services\StaffPick;

use Intervention\Image\ImageManager;

/**
 * Downsizes provider photos server-side before they are stored as BLOBs, so avatar and
 * card slots aren't served multi-megapixel originals (an 8K phone photo was ~1.1 MB for a
 * 56px circle). Scales down to fit within {@see MAX_DIMENSION} on the longest side —
 * preserving aspect ratio, never upscaling — and re-encodes as JPEG. The client keeps its
 * object-cover cropping, so the visual result is unchanged, just far smaller.
 *
 * Uses the GD driver. Formats GD can't decode (notably HEIC) throw on read; those fall back
 * to the original bytes untouched rather than failing the upload — HEIC display is a
 * separate browser limitation regardless of size.
 */
class AvatarThumbnailService
{
    /** Longest-side cap, in px. ~2x the largest avatar/banner slot for retina crispness. */
    public const MAX_DIMENSION = 512;

    /** JPEG quality for the re-encoded thumbnail. */
    private const QUALITY = 80;

    /**
     * @return array{bytes: string, mime: string} the processed bytes and their mime type
     */
    public function process(string $bytes, string $fallbackMime): array
    {
        // GD can't decode HEIC — skip it up front (attempting would emit a noisy GD warning)
        // and keep the original. HEIC display is a separate browser limitation regardless.
        if ($fallbackMime === 'image/heic') {
            return ['bytes' => $bytes, 'mime' => $fallbackMime];
        }

        try {
            // @ suppresses GD's transient "unrecognized format" warning on undecodable
            // input; the failure is handled via the exception + fallback below.
            $image = @ImageManager::gd()->read($bytes);
            $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

            return [
                'bytes' => (string) $image->toJpeg(quality: self::QUALITY),
                'mime' => 'image/jpeg',
            ];
        } catch (\Throwable $e) {
            // GD can't decode this format (e.g. HEIC) — keep the original, don't fail the upload.
            return ['bytes' => $bytes, 'mime' => $fallbackMime];
        }
    }
}
