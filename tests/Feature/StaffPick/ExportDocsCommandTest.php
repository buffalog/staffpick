<?php

namespace Tests\Feature\StaffPick;

use Tests\Feature\FeatureTest;

class ExportDocsCommandTest extends FeatureTest
{
    public function test_it_exports_a_role_guide_to_a_valid_pdf(): void
    {
        $output = storage_path('app/docs/test-scheduler-guide.pdf');

        if (is_file($output)) {
            unlink($output);
        }

        $this->artisan('staffpick:export-docs', ['role' => 'scheduler', '--output' => $output])
            ->assertSuccessful();

        $this->assertFileExists($output);
        // A real PDF starts with the %PDF- magic bytes.
        $this->assertSame('%PDF-', substr((string) file_get_contents($output), 0, 5));

        unlink($output);
    }

    public function test_it_fails_for_an_unknown_role(): void
    {
        $this->artisan('staffpick:export-docs', ['role' => 'nope'])
            ->assertFailed();
    }
}
