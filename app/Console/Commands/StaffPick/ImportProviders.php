<?php

namespace App\Console\Commands\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-off importer for the FCTS provider roster (staffpick_provider_import.csv).
 *
 * Each row → a pending, not-yet-activated Provider (staff activate after credentialing)
 * plus a live-but-unusable login (random password, no invite/verification mail). Runs in
 * console, so the Provider geocode hook never fires — the CSV's real coordinates are
 * written verbatim. Idempotent: an email that already has a provider in the tenant is
 * skipped, so a re-run after a partial failure is safe.
 */
class ImportProviders extends Command
{
    protected $signature = 'sp:import-providers {csv} {--tenant=1}';

    protected $description = 'Import the FCTS provider roster from a CSV (providers + logins).';

    /** CSV Discipline value → discipline abbreviation in sp_disciplines. */
    private const DISCIPLINE_ABBR = [
        'Physical Therapist' => 'PT',
        'Physical Therapist Assistant' => 'PTA',
        'Occupational Therapist' => 'OT',
        'Occupational Therapist Assistant' => 'OTA',
        'Speech Therapist' => 'SLP',
    ];

    public function handle(TenantPermissionService $permissions): int
    {
        $tenant = Tenant::find((int) $this->option('tenant'));
        if (! $tenant instanceof Tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $path = $this->argument('csv');
        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        // Create PTA/OTA disciplines (option A — real disciplines, PT/OT header colors via
        // the palette's abbreviation aliasing). Idempotent.
        Discipline::firstOrCreate(
            ['tenant_id' => $tenant->id, 'abbreviation' => 'PTA'],
            ['name' => 'Physical Therapist Assistant', 'is_active' => true, 'sort_order' => 4],
        );
        Discipline::firstOrCreate(
            ['tenant_id' => $tenant->id, 'abbreviation' => 'OTA'],
            ['name' => 'Occupational Therapist Assistant', 'is_active' => true, 'sort_order' => 5],
        );

        $disciplineIdByAbbr = Discipline::where('tenant_id', $tenant->id)->pluck('id', 'abbreviation')->all();
        $tierIdByName = ProviderTier::where('tenant_id', $tenant->id)->get()
            ->mapWithKeys(fn (ProviderTier $t): array => [mb_strtolower($t->name) => $t->id])->all();

        $rows = $this->readCsv($path);

        $imported = 0;
        $skipped = [];
        $failures = [];
        $tierUnconfirmed = [];
        $contractorAssumed = 0;

        foreach ($rows as $lineNo => $r) {
            $name = trim(($r['LastName'] ?? '').', '.($r['FirstName'] ?? ''));
            $email = trim($r['Email'] ?? '');

            try {
                if ($email === '') {
                    throw new \RuntimeException('missing email');
                }
                if (Provider::where('tenant_id', $tenant->id)->where('email', $email)->exists()) {
                    $skipped[] = "{$name} ({$email}) — provider already imported";

                    continue;
                }
                // An email that already belongs to a User (but no provider) needs a manual
                // decision: link the new provider to that account without clobbering its
                // existing roles (assignTenantUserRole replaces them). Surface, don't fail.
                if (User::where('email', $email)->exists()) {
                    $skipped[] = "{$name} ({$email}) — user account already exists, needs manual link";

                    continue;
                }

                $abbr = self::DISCIPLINE_ABBR[trim($r['Discipline'] ?? '')] ?? null;
                $disciplineId = $abbr !== null ? ($disciplineIdByAbbr[$abbr] ?? null) : null;
                if ($disciplineId === null) {
                    throw new \RuntimeException("unmapped discipline '".($r['Discipline'] ?? '')."'");
                }

                $tierRaw = trim($r['StaffPickTierMapped'] ?? '');
                $tierId = $tierRaw === '' ? null : ($tierIdByName[mb_strtolower($tierRaw)] ?? null);
                if ($tierRaw !== '' && $tierId === null) {
                    throw new \RuntimeException("unmapped tier '{$tierRaw}'");
                }

                // Contractor: Unknown/Yes → true (platform-wide default; assumption logged).
                if (mb_strtolower(trim($r['Contractor'] ?? '')) === 'unknown') {
                    $contractorAssumed++;
                }

                $notes = trim($r['DetailNotes'] ?? '');
                if ($tierId === null) {
                    $flag = 'TIER UNCONFIRMED — imported without a StaffPick tier; pending confirmation.';
                    $notes = $notes === '' ? $flag : $flag."\n\n".$notes;
                    $tierUnconfirmed[] = $name;
                }

                DB::transaction(function () use ($r, $tenant, $disciplineId, $tierId, $notes, $email, $permissions) {
                    $provider = Provider::create([
                        'tenant_id' => $tenant->id,
                        'first_name' => trim($r['FirstName'] ?? ''),
                        'last_name' => trim($r['LastName'] ?? ''),
                        'email' => $email,
                        'phone' => trim($r['Phone'] ?? '') ?: null,
                        'address' => trim($r['StreetAddress'] ?? '') ?: null,
                        'city' => trim($r['City'] ?? '') ?: null,
                        'state' => trim($r['State'] ?? '') ?: null,
                        'zip' => trim($r['ZIP'] ?? '') ?: null,
                        'latitude' => trim($r['GeoLatitude'] ?? '') ?: null,
                        'longitude' => trim($r['GeoLongitude'] ?? '') ?: null,
                        'discipline_id' => $disciplineId,
                        'tier_id' => $tierId,
                        'is_contractor' => true,
                        'radius_preferred_miles' => 15,
                        'radius_max_miles' => 15,
                        'gender' => null,
                        'status' => Provider::STATUS_PENDING,
                        'is_active' => false,
                        'notes' => $notes ?: null,
                    ]);

                    $provider->disciplines()->sync([$disciplineId]);
                    $provider->assignPrimaryDiscipline();

                    // Live login, unusable credential, no invite/verification mail. Raw
                    // pivot+role attach — deliberately NOT addUserToTenant (that bumps
                    // seat-based subscription quantities and fires UserJoinedTenant).
                    $user = User::create([
                        'name' => trim(($r['FirstName'] ?? '').' '.($r['LastName'] ?? '')),
                        'email' => $email,
                        'password' => Hash::make(Str::random(64)),
                    ]);
                    $tenant->users()->attach($user->id, ['is_default' => true]);
                    $permissions->assignTenantUserRole($tenant, $user, TenancyPermissionConstants::ROLE_SP_PROVIDER);

                    $provider->user_id = $user->id;
                    $provider->save();
                });

                $imported++;
            } catch (Throwable $e) {
                $failures[] = "line {$lineNo} [{$name}]: ".$e->getMessage();
            }
        }

        $this->line('');
        $this->info("IMPORTED: {$imported} / ".count($rows));
        $this->info('SKIPPED (already present): '.count($skipped));
        foreach ($skipped as $s) {
            $this->line("  - {$s}");
        }
        $this->info('CONTRACTOR assumption (Unknown → contractor=true): '.$contractorAssumed.' rows');
        $this->info('TIER UNCONFIRMED ('.count($tierUnconfirmed).') — hand to Petros/Jon:');
        foreach ($tierUnconfirmed as $t) {
            $this->line("  - {$t}");
        }
        $this->info('FAILURES: '.count($failures));
        foreach ($failures as $f) {
            $this->error("  {$f}");
        }

        return count($failures) === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $head = fgetcsv($fh, 0, ',', '"', '');
        $rows = [];
        $line = 1;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $line++;
            if (count($r) === 1 && $r[0] === null) {
                continue;
            }
            $rows[$line] = array_combine($head, array_pad($r, count($head), ''));
        }
        fclose($fh);

        return $rows;
    }
}
