<?php

namespace Tests\Unit\StaffPick;

use App\Support\StaffPick\ProviderNotesPromotion;
use PHPUnit\Framework\TestCase;

/**
 * Pure string parsing — no app boot / DB, so it runs anywhere (the sp_* tables and
 * SQL Server container aren't needed to prove the regex logic).
 */
class ProviderNotesPromotionTest extends TestCase
{
    public function test_it_promotes_the_production_note_verbatim(): void
    {
        // The exact string found on provider 36 in production.
        $parsed = ProviderNotesPromotion::parse('Languages Spoken: Spanish. Can Adjust Own Service Zones: Yes.');

        $this->assertSame(['Spanish'], $parsed['languageNames']);
        $this->assertTrue($parsed['canAdjustServiceZones']);
        $this->assertNull($parsed['notes']);
    }

    public function test_it_splits_multiple_languages_and_reads_no(): void
    {
        $parsed = ProviderNotesPromotion::parse('Languages Spoken: Spanish, Haitian Creole and French. Can Adjust Own Service Zones: No.');

        $this->assertSame(['Spanish', 'Haitian Creole', 'French'], $parsed['languageNames']);
        $this->assertFalse($parsed['canAdjustServiceZones']);
        $this->assertNull($parsed['notes']);
    }

    public function test_it_preserves_surrounding_notes_and_only_strips_the_labelled_phrases(): void
    {
        $parsed = ProviderNotesPromotion::parse('Prefers morning visits. Languages Spoken: Spanish. Very reliable.');

        $this->assertSame(['Spanish'], $parsed['languageNames']);
        $this->assertNull($parsed['canAdjustServiceZones']);
        $this->assertSame('Prefers morning visits. Very reliable.', $parsed['notes']);
    }

    public function test_it_leaves_unrelated_notes_untouched(): void
    {
        $note = 'TIER UNCONFIRMED — imported without a StaffPick tier; pending confirmation.';
        $parsed = ProviderNotesPromotion::parse($note);

        $this->assertSame([], $parsed['languageNames']);
        $this->assertNull($parsed['canAdjustServiceZones']);
        $this->assertSame($note, $parsed['notes']);
    }

    public function test_it_handles_empty_and_null_notes(): void
    {
        foreach ([null, '', '   '] as $empty) {
            $parsed = ProviderNotesPromotion::parse($empty);

            $this->assertSame([], $parsed['languageNames']);
            $this->assertNull($parsed['canAdjustServiceZones']);
            $this->assertNull($parsed['notes']);
        }
    }
}
