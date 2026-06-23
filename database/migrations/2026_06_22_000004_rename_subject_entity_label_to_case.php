<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "Subjects" → "Cases" UI rename. The entity label is tenant-configurable and
 * stored in sp_tenant_configs.entity_label_subject, which was created with a DB
 * default of 'Subject'. Because every config row therefore holds a non-blank
 * 'Subject', TenantConfig::entityLabel() returns it and the code-level 'Case'
 * fallback never applies — so the resource still shows "Subjects".
 *
 * This flips existing default-valued rows to 'Case' (preserving genuine custom
 * overrides) and updates the column default so new tenants get 'Case' too.
 *
 * SQL Server stores the default in an auto-named constraint, so it must be dropped
 * by name before a new one is added. Validated on Railway (real pdo_sqlsrv); the
 * local dblib toolchain neither has these tables nor runs migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sp_tenant_configs')
            ->where('entity_label_subject', 'Subject')
            ->update(['entity_label_subject' => 'Case']);

        $this->setColumnDefault('Case');
    }

    public function down(): void
    {
        DB::table('sp_tenant_configs')
            ->where('entity_label_subject', 'Case')
            ->update(['entity_label_subject' => 'Subject']);

        $this->setColumnDefault('Subject');
    }

    private function setColumnDefault(string $value): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement(<<<SQL
                DECLARE @name sysname;
                SELECT @name = dc.name
                FROM sys.default_constraints dc
                JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                WHERE dc.parent_object_id = OBJECT_ID('sp_tenant_configs') AND c.name = 'entity_label_subject';
                IF @name IS NOT NULL EXEC('ALTER TABLE sp_tenant_configs DROP CONSTRAINT [' + @name + ']');
                ALTER TABLE sp_tenant_configs ADD CONSTRAINT DF_sp_tenant_configs_entity_label_subject DEFAULT '{$value}' FOR entity_label_subject;
                SQL);
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE sp_tenant_configs ALTER COLUMN entity_label_subject SET DEFAULT '{$value}'");
        }
    }
};
