<?php

namespace Tests\Unit\StaffPick;

use App\Services\StaffPick\AvatarThumbnailService;
use PHPUnit\Framework\TestCase;

/**
 * Image processing via Imagick — no app boot or DB. Skips where imagick is absent (e.g. the
 * local dev machine); it runs in CI and on Railway, which have the extension.
 */
class AvatarThumbnailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('imagick is not installed in this environment');
        }
    }

    private function jpeg(int $w, int $h): string
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefilledrectangle($gd, 0, 0, $w, $h, imagecolorallocate($gd, 120, 60, 200));
        ob_start();
        imagejpeg($gd, null, 92);

        return (string) ob_get_clean();
    }

    private function png(int $w, int $h): string
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefilledrectangle($gd, 0, 0, $w, $h, imagecolorallocate($gd, 30, 120, 200));
        ob_start();
        imagepng($gd);

        return (string) ob_get_clean();
    }

    /** @return array{0: int, 1: int} */
    private function dims(string $bytes): array
    {
        $info = getimagesizefromstring($bytes);

        return [$info[0], $info[1]];
    }

    public function test_it_downsizes_a_large_jpeg(): void
    {
        $original = $this->jpeg(2000, 1500);
        $result = (new AvatarThumbnailService)->process($original, 'image/jpeg');

        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertLessThan(strlen($original), strlen($result['bytes']));
        [$w, $h] = $this->dims($result['bytes']);
        $this->assertLessThanOrEqual(AvatarThumbnailService::MAX_DIMENSION, max($w, $h));
    }

    public function test_it_thumbnails_a_high_resolution_jpeg_via_reduced_decode(): void
    {
        // 4000x3000 (12 MP) — the jpeg:size hint decodes at reduced scale, so this doesn't
        // materialise the full bitmap; the output is still capped to MAX_DIMENSION.
        $original = $this->jpeg(4000, 3000);
        $result = (new AvatarThumbnailService)->process($original, 'image/jpeg');

        [$w, $h] = $this->dims($result['bytes']);
        $this->assertLessThanOrEqual(AvatarThumbnailService::MAX_DIMENSION, max($w, $h));
        $this->assertLessThan(strlen($original), strlen($result['bytes']));
    }

    public function test_it_never_upscales_a_small_jpeg(): void
    {
        $small = $this->jpeg(100, 80);
        $result = (new AvatarThumbnailService)->process($small, 'image/jpeg');

        $this->assertSame([100, 80], $this->dims($result['bytes']));
    }

    public function test_it_skips_a_huge_non_jpeg(): void
    {
        // PNG has no reduced-resolution decode, so anything over the megapixel cap is left
        // untouched rather than risking an OOM-killing full decode.
        $huge = $this->png(4100, 4100); // ~16.8 MP
        $result = (new AvatarThumbnailService)->process($huge, 'image/png');

        $this->assertSame($huge, $result['bytes']);
        $this->assertSame('image/png', $result['mime']);
    }

    public function test_heic_and_undecodable_input_fall_back_to_the_original(): void
    {
        $result = (new AvatarThumbnailService)->process('not-a-real-image', 'image/heic');

        $this->assertSame('not-a-real-image', $result['bytes']);
        $this->assertSame('image/heic', $result['mime']);
    }
}
