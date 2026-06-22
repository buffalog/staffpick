<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Provider;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Tests\Feature\FeatureTest;

class DashboardNavigationTest extends FeatureTest
{
    /**
     * Single getNavigation() pass — Filament memoizes navigation per process, so
     * splitting these assertions across methods produces stale/empty groups.
     */
    public function test_dashboard_sidebar_navigation_structure(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createTenantAdmin($tenant);
        // A linked provider record for the admin (kept for parity with real tenants;
        // the provider-only pages no longer register in the sidebar regardless).
        Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        /** @var array<int, NavigationGroup> $groups */
        $groups = array_values(Filament::getNavigation());

        $order = array_map(fn (NavigationGroup $g): string => $g->getLabel() ?? '(ungrouped)', $groups);
        $items = [];
        $byLabel = [];
        foreach ($groups as $group) {
            $label = $group->getLabel() ?? '(ungrouped)';
            // getItems() is keyed by item name — values() for a positional list.
            $items[$label] = collect($group->getItems())->map(fn ($i): string => $i->getLabel())->values()->all();
            $byLabel[$label] = $group;
        }

        // Group order, with Support (Help) pinned to the bottom.
        $this->assertSame([
            '(ungrouped)',
            'Dispatch',
            'Credentialing',
            'Settings',
            'Administration',
            'Support',
        ], $order);
        $this->assertSame('Support', end($order));

        // Item placement.
        $this->assertContains('Dashboard', $items['(ungrouped)']);
        $this->assertSame('Board', $items['Dispatch'][0]);
        foreach (['Board', 'Intake Requests', 'Providers', 'Referral Sources', 'Cases'] as $item) {
            $this->assertContains($item, $items['Dispatch']);
        }
        $this->assertContains('Credentialing', $items['Credentialing']);
        // "My Account" group removed: ProviderProfile + MyOffers no longer register in
        // the sidebar (reached via the user/avatar menu instead).
        $this->assertArrayNotHasKey('My Account', $items);
        foreach (['Credentialing Policies', 'Slack Integration', 'Single Sign-On'] as $item) {
            $this->assertContains($item, $items['Settings']);
        }
        foreach (['Users', 'Invitations'] as $item) {
            $this->assertContains($item, $items['Administration']);
        }
        $this->assertSame(['Help'], $items['Support']);

        // Settings starts collapsed; Dispatch and Administration stay expanded.
        $this->assertTrue($byLabel['Settings']->isCollapsed());
        $this->assertFalse($byLabel['Dispatch']->isCollapsed());
        $this->assertFalse($byLabel['Administration']->isCollapsed());
    }
}
