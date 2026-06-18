<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Language;
use Database\Seeders\LanguagesSeeder;
use Tests\Feature\FeatureTest;

class LanguagesSeederTest extends FeatureTest
{
    public function test_it_seeds_the_full_language_list_idempotently(): void
    {
        // Global lookup, shared with no rollback — reset for a deterministic count.
        Language::query()->delete();

        (new LanguagesSeeder)->run();

        $this->assertSame(38, Language::count());

        // South Florida priority languages, plus the Chinese variants that need ISO 639-3.
        $this->assertSame('es', Language::where('name', 'Spanish')->value('code'));
        $this->assertSame('ht', Language::where('name', 'Haitian Creole')->value('code'));
        $this->assertSame('pt', Language::where('name', 'Portuguese')->value('code'));
        $this->assertSame('cmn', Language::where('name', 'Mandarin Chinese')->value('code'));
        $this->assertSame('yue', Language::where('name', 'Cantonese')->value('code'));
        $this->assertSame('ase', Language::where('name', 'Sign Language (ASL)')->value('code'));
        $this->assertNotNull(Language::where('name', 'Other')->first());

        // Re-running updates in place rather than duplicating (code is unique).
        (new LanguagesSeeder)->run();

        $this->assertSame(38, Language::count());
    }
}
