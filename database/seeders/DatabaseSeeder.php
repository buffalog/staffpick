<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->callOnce([
            IntervalsSeeder::class,
            CurrenciesSeeder::class,
            OAuthLoginProvidersSeeder::class,
            PaymentProvidersSeeder::class,
            RolesAndPermissionsSeeder::class,
            EmailProvidersSeeder::class,
            VerificationProvidersSeeder::class,
            LanguagesSeeder::class,
        ]);

        // Refresh the StaffPick taxonomy (disciplines, tiers, credential types, reasons)
        // for every tenant on each deploy. NOT callOnce — new tenants and newly added
        // taxonomy rows must land on every boot. Idempotent, and preserves admin-tuned
        // credential-type fields (see TenantTaxonomySeeder).
        $this->call(TenantTaxonomySeeder::class);

        // Load/refresh the global ZIP-centroid table for the self-hosted geocoder. Idempotent
        // upsert from the committed CSV, no egress. Not in TestingDatabaseSeeder (the suite
        // seeds its own sentinel centroids).
        $this->call(ZipCentroidSeeder::class);
    }
}
