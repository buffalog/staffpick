<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * tenants.domain is a nullable column that 2025_03_08_165548_add_domain_to_tenants_table
 * gave a plain unique index (tenants_domain_unique). Tenants are identified by uuid and
 * domain is optional — TenantCreationService never sets it — so every tenant beyond the
 * first holds NULL there.
 *
 * Replace the plain unique with a PARTIAL one (WHERE domain IS NOT NULL) so any number of
 * domainless tenants are allowed while non-null domains stay unique.
 *
 * Postgres note: `$table->unique()` is emitted as ADD CONSTRAINT, so the backing index is
 * owned by a constraint and DROP INDEX refuses it — the constraint has to go first. The
 * partial index this creates is free-standing, so down() drops it as an index.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_domain_unique');
        DB::statement('DROP INDEX IF EXISTS tenants_domain_unique');
        DB::statement('CREATE UNIQUE INDEX tenants_domain_unique ON tenants (domain) WHERE domain IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tenants_domain_unique');
        DB::statement('ALTER TABLE tenants ADD CONSTRAINT tenants_domain_unique UNIQUE (domain)');
    }
};
