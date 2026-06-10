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
        // Patient satisfaction surveys sent after an assignment completes; the
        // responses feed the weekly rating aggregation. tenant_id carries no FK
        // (SQL Server cascade-path constraint — see CLAUDE.md).
        Schema::create('sp_provider_surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('subject_id');
            $table->tinyInteger('rating')->nullable(); // 1–5, set on response
            $table->text('comment')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('delivery_channel')->nullable(); // sms, email
            $table->string('status')->default('pending'); // pending, sent, responded, bounced
            $table->timestamps();

            $table->index('provider_id');
            $table->index('assignment_id');
            $table->index('responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sp_provider_surveys');
    }
};
