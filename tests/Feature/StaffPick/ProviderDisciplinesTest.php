<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use Tests\Feature\FeatureTest;

class ProviderDisciplinesTest extends FeatureTest
{
    private function discipline(int $tenantId, string $name): Discipline
    {
        return Discipline::create(['tenant_id' => $tenantId, 'name' => $name]);
    }

    public function test_factory_mirrors_discipline_id_into_the_pivot_as_primary(): void
    {
        $tenant = $this->createTenant();
        $pt = $this->discipline($tenant->id, 'Physical Therapy');

        $provider = Provider::factory()->create(['tenant_id' => $tenant->id, 'discipline_id' => $pt->id]);

        $this->assertSame([$pt->id], $provider->disciplines()->pluck('sp_disciplines.id')->all());
        $this->assertSame($pt->id, (int) $provider->disciplines()->wherePivot('is_primary', true)->value('sp_disciplines.id'));
    }

    public function test_existing_primary_is_kept_when_still_held(): void
    {
        $tenant = $this->createTenant();
        $pt = $this->discipline($tenant->id, 'Physical Therapy');
        $ot = $this->discipline($tenant->id, 'Occupational Therapy');

        $provider = Provider::factory()->create(['tenant_id' => $tenant->id, 'discipline_id' => $pt->id]);

        $provider->disciplines()->sync([$pt->id, $ot->id]);
        $provider->assignPrimaryDiscipline();
        $provider->refresh();

        $this->assertSame($pt->id, $provider->discipline_id);
        $this->assertEqualsCanonicalizing([$pt->id, $ot->id], $provider->disciplines()->pluck('sp_disciplines.id')->all());
        $this->assertSame($pt->id, (int) $provider->disciplines()->wherePivot('is_primary', true)->value('sp_disciplines.id'));
    }

    public function test_primary_falls_back_to_first_when_old_primary_is_dropped(): void
    {
        $tenant = $this->createTenant();
        $pt = $this->discipline($tenant->id, 'Physical Therapy');
        $ot = $this->discipline($tenant->id, 'Occupational Therapy');

        $provider = Provider::factory()->create(['tenant_id' => $tenant->id, 'discipline_id' => $pt->id]);

        $provider->disciplines()->sync([$ot->id]);
        $provider->assignPrimaryDiscipline();
        $provider->refresh();

        $this->assertSame($ot->id, $provider->discipline_id);
        $this->assertSame($ot->id, (int) $provider->disciplines()->wherePivot('is_primary', true)->value('sp_disciplines.id'));
    }

    public function test_empty_set_clears_the_primary_column(): void
    {
        $tenant = $this->createTenant();
        $pt = $this->discipline($tenant->id, 'Physical Therapy');

        $provider = Provider::factory()->create(['tenant_id' => $tenant->id, 'discipline_id' => $pt->id]);

        $provider->disciplines()->sync([]);
        $provider->assignPrimaryDiscipline();
        $provider->refresh();

        $this->assertNull($provider->discipline_id);
        $this->assertCount(0, $provider->disciplines()->get());
    }
}
