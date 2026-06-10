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
        Schema::table('sp_subjects', function (Blueprint $table) {
            // Optional email contact, used as the survey delivery fallback when no
            // phone is available (SMS is preferred).
            $table->string('email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sp_subjects', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
