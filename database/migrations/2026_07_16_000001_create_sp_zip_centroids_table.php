<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PUBLIC reference data (US Census ZCTA centroids + GeoNames gap-fill). GLOBAL, not
        // tenant-scoped: no tenant_id, and the model must NOT implement BearsTenantPhi. A ZIP
        // centroid is public info, not PHI, and geocoding runs on model save in contexts with
        // no tenant (queue, console, seeder), so this MUST stay readable with no tenant context.
        Schema::create('sp_zip_centroids', function (Blueprint $table) {
            $table->char('zip', 5)->primary();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('source', 16); // 'census' (public domain) | 'geonames' (CC-BY 4.0)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_zip_centroids');
    }
};
