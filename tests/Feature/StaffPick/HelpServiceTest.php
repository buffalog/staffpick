<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Provider;
use App\Services\StaffPick\HelpService;
use Filament\Facades\Filament;
use Tests\Feature\FeatureTest;

class HelpServiceTest extends FeatureTest
{
    private function help(): HelpService
    {
        return app(HelpService::class);
    }

    public function test_manifest_exposes_the_three_role_tracks_with_topics(): void
    {
        $help = $this->help();

        $this->assertEqualsCanonicalizing(
            ['scheduler', 'clinician', 'referral-source'],
            $help->roles(),
        );

        $this->assertCount(7, $help->topics('scheduler'));
        $this->assertCount(6, $help->topics('clinician'));
        $this->assertCount(4, $help->topics('referral-source'));
    }

    public function test_render_converts_markdown_to_html_including_gfm_tables(): void
    {
        $rendered = $this->help()->render('scheduler', 'managing-intake-requests');

        $this->assertNotNull($rendered);
        $this->assertStringContainsString('<h1>', $rendered['html']);
        // The status-meanings table is GFM — confirm tables render (CommonMark core would not).
        $this->assertStringContainsString('<table>', $rendered['html']);
        $this->assertStringContainsString('Pending', $rendered['html']);
    }

    public function test_render_returns_null_for_an_unknown_topic(): void
    {
        $this->assertNull($this->help()->render('scheduler', 'does-not-exist'));
    }

    public function test_search_finds_topics_by_term_within_the_role(): void
    {
        $results = $this->help()->search('scheduler', 'language warning');

        $slugs = array_column($results, 'slug');

        $this->assertContains('running-the-matching-engine', $slugs);
        $this->assertNotEmpty($results[0]['snippet']);

        // Terms only present in the clinician track must not match a scheduler search.
        $this->assertSame([], $this->help()->search('scheduler', 'zzzznotaword'));
    }

    public function test_resolve_role_maps_admins_to_scheduler(): void
    {
        $tenant = $this->createTenant();
        Filament::setTenant($tenant, isQuiet: true);
        $admin = $this->createTenantAdmin($tenant);

        $this->assertSame(HelpService::ROLE_SCHEDULER, $this->help()->resolveRoleForUser($admin));
    }

    public function test_resolve_role_maps_a_provider_user_to_clinician(): void
    {
        $tenant = $this->createTenant();
        Filament::setTenant($tenant, isQuiet: true);
        $user = $this->createUser($tenant);
        Provider::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        $this->assertSame(HelpService::ROLE_CLINICIAN, $this->help()->resolveRoleForUser($user));
    }

    public function test_resolve_role_defaults_to_referral_source_for_guests(): void
    {
        $this->assertSame(HelpService::ROLE_REFERRAL_SOURCE, $this->help()->resolveRoleForUser(null));
    }
}
