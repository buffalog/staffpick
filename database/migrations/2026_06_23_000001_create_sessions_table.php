<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent (database) session store. Railway containers are ephemeral, so the
 * default file-based sessions are wiped on every deploy — logging every user out.
 * Storing sessions in the (HIPAA BAA-covered) SQL Server database keeps users signed
 * in across deploys. user_id is a plain indexed column (no FK constraint — SQL Server
 * cascade-path rules, consistent with the rest of the schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
