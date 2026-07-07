<?php

namespace Tests\Unit\StaffPick;

use App\Services\StaffPick\AvatarThumbnailService;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;

/**
 * Pure image processing — GD only, no app boot or DB, so it runs anywhere GD is present.
 */
class AvatarThumbnailServiceTest extends TestCase
{
    private function largeJpeg(int $w = 2000, int $h = 1500): string
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefilledrectangle($gd, 0, 0, $w, $h, imagecolorallocate($gd, 120, 60, 200));
        ob_start();
        imagejpeg($gd, null, 92);

        return (string) ob_get_clean();
    }

    public function test_it_downsizes_a_large_image_within_the_cap(): void
    {
        $original = $this->largeJpeg(2000, 1500);
        $result = (new AvatarThumbnailService)->process($original, 'image/jpeg');

        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertLessThan(strlen($original), strlen($result['bytes']), 'thumbnail should be smaller');

        $out = ImageManager::gd()->read($result['bytes']);
        $this->assertLessThanOrEqual(AvatarThumbnailService::MAX_DIMENSION, $out->width());
        $this->assertLessThanOrEqual(AvatarThumbnailService::MAX_DIMENSION, $out->height());
        // Aspect ratio preserved (2000x1500 -> 512x384), not cropped to a square.
        $this->assertSame(512, $out->width());
        $this->assertSame(384, $out->height());
    }

    public function test_it_never_upscales_a_small_image(): void
    {
        $small = $this->largeJpeg(100, 80);
        $result = (new AvatarThumbnailService)->process($small, 'image/jpeg');

        $out = ImageManager::gd()->read($result['bytes']);
        $this->assertSame(100, $out->width());
        $this->assertSame(80, $out->height());
    }

    public function test_it_skips_images_over_the_megapixel_cap(): void
    {
        // Just over MAX_MEGAPIXELS (~16.8 MP) — must NOT be decoded (the OOM guard), so the
        // original bytes are returned untouched.
        $huge = $this->largeJpeg(4100, 4100);
        $result = (new AvatarThumbnailService)->process($huge, 'image/jpeg');

        $this->assertSame($huge, $result['bytes']);
        $this->assertSame('image/jpeg', $result['mime']);
    }

    public function test_undecodable_input_falls_back_to_the_original_bytes(): void
    {
        // Stands in for a HEIC (or any format GD can't read): must not throw, keeps original.
        $result = (new AvatarThumbnailService)->process('not-a-real-image', 'image/heic');

        $this->assertSame('not-a-real-image', $result['bytes']);
        $this->assertSame('image/heic', $result['mime']);
    }
}
