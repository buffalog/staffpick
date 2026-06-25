<?php

namespace Tests\Unit;

use App\Models\StaffPick\Provider;
use PHPUnit\Framework\TestCase;

class ProviderColorTest extends TestCase
{
    public function test_hsl_to_hex_matches_known_value(): void
    {
        // index 0 → hue 0, the auto-assign saturation/lightness (65/48).
        $this->assertSame('#CA2B2B', Provider::hslToHex(0, 65, 48));
    }

    public function test_golden_angle_hues_are_always_valid_hex(): void
    {
        foreach (range(0, 12) as $index) {
            $hex = Provider::hslToHex(fmod($index * 137.508, 360), 65, 48);
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $hex);
        }
    }

    public function test_hex_to_rgba_converts(): void
    {
        $this->assertSame('rgba(230, 57, 70, 0.25)', Provider::hexToRgba('#E63946', 0.25));
        $this->assertSame('rgba(255, 255, 255, 1)', Provider::hexToRgba('#fff', 1.0));
    }

    public function test_hex_to_rgba_falls_back_on_malformed_input(): void
    {
        $this->assertSame('rgba(100, 116, 139, 0.25)', Provider::hexToRgba('not-a-color', 0.25));
    }
}
