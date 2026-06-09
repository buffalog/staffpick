<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            // SQL Server rejects any two FKs pointing to the same table, even with
            // different actions. Store trial_interval_id as a plain unsignedBigInteger
            // (no FK constraint) to avoid the cascade cycle error.
            $table->unsignedBigInteger('trial_interval_id')->nullable();
            $table->unsignedInteger('interval_count');
            $table->integer('trial_interval_count')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
