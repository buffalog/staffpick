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

    /**
     * Hard cap on source pixels we'll decode. GD decodes to a full ~4 bytes/pixel bitmap in
     * memory (a 33 MP 8K photo is ~130 MB) which can exceed the container's RAM and get the
     * process OOM-killed — an uncatchable SIGKILL that crash-loops a migration or fails a
     * request. Anything larger is left at original size rather than decoded.
     *
     * 16 MP (~64 MB bitmap) is a safe margin: this container OOM-killed decoding a 33 MP
     * (~130 MB) photo, so headroom is under ~130 MB. Standard phone photos are ~12 MP and
     * pass; genuinely huge images (8K, 48 MP) need a reduced-resolution decoder (Imagick).
     */
    public const MAX_MEGAPIXELS = 16_000_000;

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

        // OOM guard: read only the header dimensions (no decode) and skip images whose pixel
        // count would blow the memory budget when GD decodes them. See MAX_MEGAPIXELS.
        $info = @getimagesizefromstring($bytes);
        if ($info === false || (($info[0] * $info[1]) > self::MAX_MEGAPIXELS)) {
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
