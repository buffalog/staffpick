<?php

namespace App\Services\StaffPick;

/**
 * Downsizes provider photos server-side before they are stored as BLOBs, so avatar and card
 * slots aren't served multi-megapixel originals (an 8K phone photo was ~1.1 MB for a 56px
 * circle). Scales down to fit within {@see MAX_DIMENSION} — preserving aspect ratio, never
 * upscaling — and re-encodes as JPEG. The client keeps its object-cover cropping, so the
 * visual result is unchanged, just far smaller.
 *
 * Uses Imagick with a reduced-resolution JPEG decode (the jpeg:size hint): libjpeg decodes a
 * huge JPEG at ~1/8 scale directly, so an 8K / 33 MP photo materialises as ~a few MB instead
 * of a full ~130 MB bitmap that would OOM-kill the container. Non-JPEG formats have no
 * reduced-res path, so a hard megapixel cap guards those. HEIC (delegates may not decode it),
 * a missing imagick extension, and any failure fall back to the original bytes rather than
 * failing the upload.
 */
class AvatarThumbnailService
{
    /** Longest-side cap, in px. ~2x the largest avatar/banner slot for retina crispness. */
    public const MAX_DIMENSION = 512;

    /**
     * Pixel cap for formats WITHOUT a reduced-resolution decode (PNG etc.) — those decode to a
     * full ~4 bytes/pixel bitmap and could exhaust the container's RAM. JPEG is exempt: the
     * jpeg:size hint keeps its decode small at any resolution.
     */
    public const MAX_MEGAPIXELS = 16_000_000;

    /** JPEG quality for the re-encoded thumbnail. */
    private const QUALITY = 80;

    /**
     * @return array{bytes: string, mime: string} the processed bytes and their mime type
     */
    public function process(string $bytes, string $fallbackMime): array
    {
        // No imagick, or HEIC (delegates unreliable) — keep the original, don't fail the upload.
        if (! extension_loaded('imagick') || $fallbackMime === 'image/heic') {
            return ['bytes' => $bytes, 'mime' => $fallbackMime];
        }

        // Read header dimensions without decoding. Undecodable -> keep the original.
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            return ['bytes' => $bytes, 'mime' => $fallbackMime];
        }
        [$srcWidth, $srcHeight] = [$info[0], $info[1]];

        // Already within the cap — leave it untouched. Also avoids jpeg:size upscaling a
        // small source: libjpeg-turbo will scale a tiny image UP (to 2x) toward the hint.
        if (max($srcWidth, $srcHeight) <= self::MAX_DIMENSION) {
            return ['bytes' => $bytes, 'mime' => $fallbackMime];
        }

        $isJpeg = $fallbackMime === 'image/jpeg';

        // Non-JPEG decodes at full resolution, so skip a huge one (JPEG is safe via jpeg:size).
        if (! $isJpeg && (($srcWidth * $srcHeight) > self::MAX_MEGAPIXELS)) {
            return ['bytes' => $bytes, 'mime' => $fallbackMime];
        }

        $imagick = null;

        try {
            $imagick = new \Imagick;
            // Backstop so a pathological image can't exhaust memory even if a guard is missed.
            $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
            $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);

            if ($isJpeg) {
                // libjpeg decodes at the smallest 1/1..1/8 scale >= this hint, so a huge JPEG
                // never fully materialises in memory.
                $imagick->setOption('jpeg:size', self::MAX_DIMENSION.'x'.self::MAX_DIMENSION);
            }

            $imagick->readImageBlob($bytes);

            if (method_exists($imagick, 'autoOrient')) {
                $imagick->autoOrient(); // bake in EXIF rotation before metadata is stripped
            }

            // Only downscale — never upscale a small image. thumbnailImage also strips metadata.
            if ($imagick->getImageWidth() > self::MAX_DIMENSION || $imagick->getImageHeight() > self::MAX_DIMENSION) {
                $imagick->thumbnailImage(self::MAX_DIMENSION, self::MAX_DIMENSION, true);
            }

            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(self::QUALITY);

            return ['bytes' => $imagick->getImageBlob(), 'mime' => 'image/jpeg'];
        } catch (\Throwable $e) {
            return ['bytes' => $bytes, 'mime' => $fallbackMime];
        } finally {
            $imagick?->clear();
        }
    }
}
