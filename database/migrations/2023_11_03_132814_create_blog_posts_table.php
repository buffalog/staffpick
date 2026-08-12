<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('user_id')->constrained();
            // Second FK to users, left unconstrained to match author_id's original shape
            $table->unsignedBigInteger('author_id')->nullable();
            $table->foreignId('blog_post_category_id')->nullable()->constrained();

            // fulltext index not supported by the sqlite driver
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['title', 'body']);
            }

            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
