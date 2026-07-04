<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voluntary provider profile photo (self-uploaded, or added by staff on their behalf).
 * Nullable path to a standard Filament FileUpload; no ->after() as SQL Server ignores
 * column ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->string('photo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
