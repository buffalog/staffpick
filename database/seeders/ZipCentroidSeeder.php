<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Loads sp_zip_centroids from the committed CSV on every deploy (idempotent upsert, no egress).
 * Wired into DatabaseSeeder so it rides the existing `php artisan db:seed --force` in start.sh.
 * Deliberately NOT added to TestingDatabaseSeeder: the suite seeds its own sentinel centroids,
 * and loading all ~41k rows would slow every run and break the unknown-ZIP assertions.
 */
class ZipCentroidSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('staffpick:import-zip-centroids');
    }
}
