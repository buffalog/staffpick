<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot pre-migration collision audit. Run on Railway staging before deploying
 * the collision-hardening migrations. Safe: read-only, no writes.
 */
class CheckCollisionDuplicates extends Command
{
    protected $signature   = 'staffpick:check-collision-duplicates';
    protected $description = 'Audit sp_providers and sp_provider_credentials for duplicates that would block the collision-hardening migrations.';

    public function handle(): int
    {
        $this->info('--- sp_providers: duplicate (tenant_id, email) ---');
        $providerDupes = DB::select("
            SELECT tenant_id, email, COUNT(*) AS cnt
            FROM sp_providers
            WHERE email IS NOT NULL AND deleted_at IS NULL
            GROUP BY tenant_id, email
            HAVING COUNT(*) > 1
        ");
        if (empty($providerDupes)) {
            $this->info('  CLEAN — no duplicates.');
        } else {
            $this->error('  DUPLICATES FOUND:');
            foreach ($providerDupes as $r) {
                $this->line("  tenant={$r->tenant_id}  email={$r->email}  count={$r->cnt}");
            }
        }

        $this->newLine();

        $this->info('--- sp_provider_credentials: duplicate (provider_id, document_type_id) ---');
        $credDupes = DB::select("
            SELECT provider_id, document_type_id, COUNT(*) AS cnt
            FROM sp_provider_credentials
            GROUP BY provider_id, document_type_id
            HAVING COUNT(*) > 1
        ");
        if (empty($credDupes)) {
            $this->info('  CLEAN — no duplicates.');
        } else {
            $this->error('  DUPLICATES FOUND:');
            foreach ($credDupes as $r) {
                $this->line("  provider={$r->provider_id}  doc_type={$r->document_type_id}  count={$r->cnt}");
            }
        }

        $anyDupes = ! empty($providerDupes) || ! empty($credDupes);

        $this->newLine();

        if ($anyDupes) {
            $this->error('ACTION REQUIRED: resolve duplicates before deploying collision migrations.');
            return Command::FAILURE;
        }

        $this->info('All clear. Safe to deploy collision-hardening migrations.');
        return Command::SUCCESS;
    }
}
