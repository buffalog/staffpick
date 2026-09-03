<?php

namespace Tests\Feature\Filament;

use App\Filament\Dashboard\Resources\IntakeRequests\Pages\CreateIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\EditIntakeRequest;
use App\Models\StaffPick\IntakeRequest;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Filament binds getCreateFormAction()/getSaveFormAction() with ->submit() whenever the page
 * renders a <form> wrapper, and a submit button only fires from inside that element. Header
 * actions render outside it, so a page that relocates those buttons to the header without
 * turning the wrapper off ships a dead type="submit" button: no record, no validation error,
 * no exception. Asserting the action exists (or calling it directly) does not catch that —
 * these tests read the rendered markup instead.
 */
class HeaderFormActionsTest extends FeatureTest
{
    private function actAsTenant(): Tenant
    {
        $tenant = $this->createTenant();
        $this->actingAs($this->createTenantAdmin($tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        return $tenant;
    }

    public function test_the_rendered_create_button_calls_livewire_rather_than_submitting(): void
    {
        $this->actAsTenant();

        $html = Livewire::test(CreateIntakeRequest::class)->html();

        $this->assertStringContainsString('wire:click="create"', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
    }

    public function test_the_rendered_save_button_calls_livewire_rather_than_submitting(): void
    {
        $tenant = $this->actAsTenant();

        $record = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ]);

        $html = Livewire::test(EditIntakeRequest::class, ['record' => $record->getKey()])->html();

        $this->assertStringContainsString('wire:click="save"', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
    }

    /**
     * Repo-wide guard for the same defect: every page that moves a form-submitting action into
     * getHeaderActions() must also opt out of the <form> wrapper.
     */
    public function test_every_page_with_a_form_action_in_its_header_disables_the_form_wrapper(): void
    {
        $offenders = [];

        foreach ($this->filamentPageFiles() as $file) {
            $source = file_get_contents($file);

            if (! preg_match('/function getHeaderActions\(\).*?\n    }/s', $source, $matches)) {
                continue;
            }

            if (! preg_match('/get(Create|Save|Submit)FormAction\(\)/', $matches[0])) {
                continue;
            }

            if (! preg_match('/function hasFormWrapper\(\)\s*:\s*bool\s*\{\s*return false;/s', $source)) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'These pages put a form-submitting action in the header while still rendering a',
            '<form> wrapper, so the button is inert. Add `public function hasFormWrapper(): bool',
            '{ return false; }` to each:',
            ...$offenders,
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function filamentPageFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        $this->assertNotEmpty($files, 'Expected to find Filament page classes to scan.');

        return $files;
    }
}
