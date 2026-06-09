<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_item_user_upvotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_item_id')->constrained()->cascadeOnDelete();
            // SQL Server cascade cycle: user_id stored as plain integer
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_item_user_upvotes');
    }
};
