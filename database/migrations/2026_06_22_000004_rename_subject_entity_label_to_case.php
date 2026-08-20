<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "Subjects" → "Cases" UI rename. The entity label is tenant-configurable and stored in
 * sp_tenant_configs.entity_label_subject, which was created with a DB default of 'Subject'.
 * Because every config row therefore holds a non-blank 'Subject',
 * TenantConfig::entityLabel() returns it and the code-level 'Case' fallback never applies —
 * so the resource still shows "Subjects".
 *
 * This flips existing default-valued rows to 'Case' (preserving genuine custom overrides)
 * and updates the column default so new tenants get 'Case' too.
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
        DB::statement("ALTER TABLE sp_tenant_configs ALTER COLUMN entity_label_subject SET DEFAULT '{$value}'");
    }
};
