<?php

namespace Tests\Unit\StaffPick;

use App\Filament\Dashboard\Support\DisciplinePalette;
use PHPUnit\Framework\TestCase;

class DisciplinePaletteTest extends TestCase
{
    public function test_it_maps_the_three_disciplines_to_their_approved_hex_values(): void
    {
        $this->assertSame(['bg' => '#E1F5EE', 'text' => '#085041'], DisciplinePalette::forAbbreviation('PT'));
        $this->assertSame(['bg' => '#FAECE7', 'text' => '#4A1B0C'], DisciplinePalette::forAbbreviation('OT'));
        $this->assertSame(['bg' => '#EEEDFE', 'text' => '#26215C'], DisciplinePalette::forAbbreviation('SLP'));
    }

    public function test_assistant_disciplines_share_their_lead_color(): void
    {
        $this->assertSame(DisciplinePalette::forAbbreviation('PT'), DisciplinePalette::forAbbreviation('PTA'));
        $this->assertSame(DisciplinePalette::forAbbreviation('OT'), DisciplinePalette::forAbbreviation('OTA'));
    }

    public function test_it_is_case_and_whitespace_insensitive(): void
    {
        $this->assertSame(['bg' => '#E1F5EE', 'text' => '#085041'], DisciplinePalette::forAbbreviation('  pt '));
    }

    public function test_unmapped_or_null_disciplines_fall_back_to_neutral(): void
    {
        $neutral = ['bg' => '#F1F5F9', 'text' => '#334155'];
        $this->assertSame($neutral, DisciplinePalette::forAbbreviation('RD'));
        $this->assertSame($neutral, DisciplinePalette::forAbbreviation(null));
        $this->assertSame($neutral, DisciplinePalette::forAbbreviation(''));
    }
}
