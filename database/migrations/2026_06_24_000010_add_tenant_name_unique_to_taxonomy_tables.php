<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Block duplicate active taxonomy names within a tenant. Each of the nine sp_* taxonomy
 * tables gets a partial unique index on (tenant_id, name) WHERE is_active — so two active
 * entries can't share a name, but inactive (logically deleted) rows don't permanently claim
 * a name slot. Filament surfaces the DB violation as a validation error, so no app-layer
 * guard.
 *
 * The predicate is bare `WHERE is_active`, not `WHERE is_active = 1`: the column is a real
 * Postgres boolean and `boolean = integer` has no operator.
 */
return new class extends Migration
{
    /** @var array<string, string> table => index name */
    private array $indexes = [
        'sp_disciplines' => 'sp_disciplines_tenant_name_unique',
        'sp_specialties' => 'sp_specialties_tenant_name_unique',
        'sp_credential_document_types' => 'sp_credential_document_types_tenant_name_unique',
        'sp_on_hold_reasons' => 'sp_on_hold_reasons_tenant_name_unique',
        'sp_cancellation_reasons' => 'sp_cancellation_reasons_tenant_name_unique',
        'sp_visit_types' => 'sp_visit_types_tenant_name_unique',
        'sp_provider_tiers' => 'sp_provider_tiers_tenant_name_unique',
        'sp_insurance_types' => 'sp_insurance_types_tenant_name_unique',
        'sp_decline_reasons' => 'sp_decline_reasons_tenant_name_unique',
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $index) {
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} (tenant_id, name) WHERE is_active");
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
