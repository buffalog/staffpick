<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy disk-path `photo` string column. It was retired when provider photos
 * moved to the sp_provider_photos BLOB table, but leaving it in place shadowed the new
 * `photo()` HasOne relation: Eloquent resolves `$provider->photo` to the (now always null)
 * column attribute instead of the relation, so uploaded photos never rendered on the card
 * or profile. Removing the column lets `$provider->photo` fall through to the relation.
 *
 * Plain nullable string, no index or default constraint — a straightforward drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }

    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->string('photo')->nullable();
        });
    }
};
