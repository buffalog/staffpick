<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text detail for a provider↔specialty link — used to capture the clinician's
     * write-in value when they select the "Other (write in)" specialty.
     */
    public function up(): void
    {
        Schema::table('sp_provider_specialties', function (Blueprint $table) {
            $table->string('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_provider_specialties', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
