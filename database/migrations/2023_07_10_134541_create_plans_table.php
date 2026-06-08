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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('interval_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->boolean('is_active')->default(true);
            $table->boolean('has_trial')->default(false);
            // SQL Server does not allow multiple cascade paths to the same table.
            // trial_interval_id is nullable, so nullOnDelete is safe and correct.
            $table->foreignId('trial_interval_id')->nullable()->constrained('intervals')->nullOnDelete()->noActionOnUpdate();
            $table->unsignedInteger('interval_count');
            $table->integer('trial_interval_count')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
