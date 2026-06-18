<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\Help;
use App\Livewire\StaffPick\HelpSlideOver;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class HelpCenterTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    public function test_help_page_renders_the_scheduler_track_for_an_admin(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(Help::class)
            ->assertSuccessful()
            ->assertSet('topic', 'getting-started')
            ->assertSee('Getting Started')
            ->assertSee('The Dispatch Board');
    }

    public function test_selecting_a_topic_renders_its_content(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(Help::class)
            ->call('selectTopic', 'credentialing')
            ->assertSet('topic', 'credentialing')
            ->assertSee('Verify Now');
    }

    public function test_search_returns_matching_topics(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(Help::class)
            ->set('query', 'expanded radius')
            ->assertSee('Managing Offers');
    }

    public function test_dashboard_page_renders_with_the_global_help_slide_over_hook(): void
    {
        // A real request exercises the BODY_END render hook (the global slide-over) that
        // Livewire::test() skips — a bad component reference would 500 every page.
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $this->get(Help::getUrl(panel: 'dashboard', tenant: $this->tenant))
            ->assertOk();
    }

    public function test_slide_over_opens_to_a_topic_on_event(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(HelpSlideOver::class)
            ->assertSet('open', false)
            ->call('openHelp', 'scheduler/dispatch-board')
            ->assertSet('open', true)
            ->assertSet('role', 'scheduler')
            ->assertSet('slug', 'dispatch-board')
            ->assertSee('Needs Attention');
    }
}
