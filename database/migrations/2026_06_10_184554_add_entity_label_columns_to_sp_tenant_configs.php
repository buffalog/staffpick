<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tenant-configurable singular entity labels. Plural forms are derived at
        // the presentation layer (Str::plural). Defaults match the strings that
        // were previously hardcoded in the Filament resources.
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->string('entity_label_provider')->default('Provider');
            $table->string('entity_label_subject')->default('Subject');
            $table->string('entity_label_intake_request')->default('Intake Request');
            $table->string('entity_label_discipline')->default('Discipline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->dropColumn([
                'entity_label_provider',
                'entity_label_subject',
                'entity_label_intake_request',
                'entity_label_discipline',
            ]);
        });
    }
};
