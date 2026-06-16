<?php

namespace Database\Seeders;

use App\Constants\VerificationProviderConstants;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VerificationProvidersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('verification_providers')->upsert([
            [
                'name' => 'Pingram',
                'slug' => VerificationProviderConstants::PINGRAM_SLUG,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['slug']);

        // Retire the legacy Twilio provider row.
        DB::table('verification_providers')->where('slug', 'twilio')->delete();
    }
}
