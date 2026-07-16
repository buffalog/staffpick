<?php

namespace App\Console\Commands\StaffPick;

use App\Models\StaffPick\ZipCentroid;
use Illuminate\Console\Command;

/**
 * Load/refresh sp_zip_centroids from the committed CSV. Idempotent (upsert on zip). No network:
 * the CSV is public Census + GeoNames data committed to the repo, so a deploy geocodes with zero
 * egress. Run in the release phase after migrate.
 */
class ImportZipCentroids extends Command
{
    protected $signature = 'staffpick:import-zip-centroids {--path= : CSV path (default database/data/zip_centroids.csv)}';

    protected $description = 'Load/refresh sp_zip_centroids from the committed public-domain + CC-BY CSV (no egress).';

    // SQL Server caps a statement at 2100 bind params. 400 rows x 4 cols = 1600, safely under.
    private const CHUNK = 400;

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/zip_centroids.csv');

        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);

        if ($header !== ['zip', 'latitude', 'longitude', 'source']) {
            $this->error('Unexpected header. Expected: zip,latitude,longitude,source');
            fclose($handle);

            return self::FAILURE;
        }

        $rows = [];
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $zip = str_pad(trim((string) ($row[0] ?? '')), 5, '0', STR_PAD_LEFT);

            if (! preg_match('/^\d{5}$/', $zip) || ! is_numeric($row[1] ?? null) || ! is_numeric($row[2] ?? null)) {
                continue;
            }

            $rows[] = [
                'zip' => $zip,
                'latitude' => (float) $row[1],
                'longitude' => (float) $row[2],
                'source' => (string) ($row[3] ?? ''),
            ];

            if (count($rows) >= self::CHUNK) {
                ZipCentroid::upsert($rows, ['zip'], ['latitude', 'longitude', 'source']);
                $count += count($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            ZipCentroid::upsert($rows, ['zip'], ['latitude', 'longitude', 'source']);
            $count += count($rows);
        }

        fclose($handle);

        $this->info("Imported/updated {$count} ZIP centroids.");

        return self::SUCCESS;
    }
}
