<?php

namespace App\Console\Commands;

use App\Services\StaffPick\HelpPdfExporter;
use App\Services\StaffPick\HelpService;
use Illuminate\Console\Command;

/**
 * Exports a help role's guide (every topic in manifest order, with a cover page) to a
 * single PDF — by default storage/app/docs/{role}-guide.pdf.
 */
class ExportDocs extends Command
{
    protected $signature = 'staffpick:export-docs {role : scheduler|clinician|referral-source} {--output= : Write to this path instead of storage/app/docs}';

    protected $description = 'Export a StaffPick help guide (by role) to PDF.';

    public function handle(HelpService $help, HelpPdfExporter $exporter): int
    {
        $role = $this->argument('role');

        if (! $help->roleExists($role)) {
            $this->error("Unknown role [{$role}]. Available: ".implode(', ', $help->roles()));

            return self::FAILURE;
        }

        $path = $exporter->save($role, $this->option('output') ?: null);

        $this->info("Exported the {$help->roleLabel($role)} to {$path}");

        return self::SUCCESS;
    }
}
