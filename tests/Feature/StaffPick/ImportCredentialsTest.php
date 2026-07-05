<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use Database\Seeders\TenantTaxonomySeeder;
use Tests\Feature\FeatureTest;

/**
 * Covers the CliniConnects credential import (step 6): column→canonical-type mapping,
 * discipline-routed license columns, "On file" → present/no-expiry, dates carried as-is,
 * DO NOT USE dropped, and the hard stop on an unmapped column.
 */
class ImportCredentialsTest extends FeatureTest
{
    private function writeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cred').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    private function seedAndProviders(): Tenant
    {
        $tenant = $this->createTenant();
        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $ptId = Discipline::where('tenant_id', $tenant->id)->where('abbreviation', 'PT')->value('id');
        Provider::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'Adam', 'last_name' => 'Pizow', 'discipline_id' => $ptId]);

        return $tenant;
    }

    public function test_imports_mapped_credentials_with_dates_and_on_file(): void
    {
        $tenant = $this->seedAndProviders();
        $provider = Provider::where('tenant_id', $tenant->id)->first();

        // PTA discipline routes the license columns onto State License (PT).
        $csv = $this->writeCsv(
            "First Name,Last Name,Discipline,CPR/BLS,Social Security Card,NSO,Professional License,DO NOT USE\n".
            "Adam,Pizow,PTA,2/1/27,On file,5/25/28,3/10/26,On file\n"
        );

        $this->artisan('sp:import-credentials', ['csv' => $csv, '--tenant' => $tenant->id])
            ->assertExitCode(0);

        $typeId = fn (string $name): int => (int) CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', $name)->value('id');

        // Date carried through.
        $cpr = ProviderCredential::where('provider_id', $provider->id)->where('document_type_id', $typeId('CPR/BLS'))->first();
        $this->assertNotNull($cpr);
        $this->assertSame('2027-02-01', $cpr->expires_at->toDateString());

        // "On file" → present, null expiry (not expired/invalid).
        $ssn = ProviderCredential::where('provider_id', $provider->id)->where('document_type_id', $typeId('Social Security Card'))->first();
        $this->assertNotNull($ssn);
        $this->assertNull($ssn->expires_at);

        // NSO absorbed into Liability/Malpractice Insurance.
        $this->assertDatabaseHas('sp_provider_credentials', [
            'provider_id' => $provider->id,
            'document_type_id' => $typeId('Liability/Malpractice Insurance'),
        ]);

        // Professional License routed onto State License (PT) for a PTA.
        $this->assertDatabaseHas('sp_provider_credentials', [
            'provider_id' => $provider->id,
            'document_type_id' => $typeId('State License (PT)'),
        ]);

        // DO NOT USE dropped — 4 real credentials only (CPR, SSN, NSO->Liability, license).
        $this->assertSame(4, ProviderCredential::where('provider_id', $provider->id)->count());
    }

    public function test_unmapped_column_stops_the_import_and_writes_nothing(): void
    {
        $tenant = $this->seedAndProviders();
        $provider = Provider::where('tenant_id', $tenant->id)->first();

        $csv = $this->writeCsv(
            "First Name,Last Name,Discipline,CPR/BLS,Totally New Column\n".
            "Adam,Pizow,PTA,2/1/27,On file\n"
        );

        $this->artisan('sp:import-credentials', ['csv' => $csv, '--tenant' => $tenant->id])
            ->assertExitCode(1);

        $this->assertSame(0, ProviderCredential::where('provider_id', $provider->id)->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $tenant = $this->seedAndProviders();
        $provider = Provider::where('tenant_id', $tenant->id)->first();

        $csv = $this->writeCsv(
            "First Name,Last Name,Discipline,CPR/BLS\n".
            "Adam,Pizow,PTA,2/1/27\n"
        );

        $this->artisan('sp:import-credentials', ['csv' => $csv, '--tenant' => $tenant->id, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, ProviderCredential::where('provider_id', $provider->id)->count());
    }
}
