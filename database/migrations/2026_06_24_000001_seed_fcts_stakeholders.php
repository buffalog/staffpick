<?php

use Database\Seeders\FctsStakeholderSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds FCTS stakeholder accounts (Jon, Petros, Ed) with full SP role access.
 * Runs the idempotent FctsStakeholderSeeder via migration so Railway picks it up.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seeder = app(FctsStakeholderSeeder::class);
        $seeder->run();
    }

    public function down(): void
    {
        // Intentionally a no-op — do not delete stakeholder accounts on rollback.
    }
};
