<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discipline ↔ Specialty pivot: scopes which specialties apply to each discipline
     * (e.g. PT → Orthopedics, OT → Hand Therapy) so forms can filter the specialty list
     * by the chosen discipline. Plain columns, no FK (SQL Server cascade-path rule —
     * CLAUDE.md), mirroring the other sp_* pivots.
     */
    public function up(): void
    {
        Schema::create('sp_discipline_specialties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discipline_id');
            $table->unsignedBigInteger('specialty_id');
            $table->timestamps();

            $table->unique(['discipline_id', 'specialty_id']);
            $table->index('specialty_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_discipline_specialties');
    }
};
