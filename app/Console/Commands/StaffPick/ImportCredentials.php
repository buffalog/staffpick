<?php

namespace App\Console\Commands\StaffPick;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-off importer for the 151-person CliniConnects credential export
 * (credentials_known_providers.csv). One row per person, one column per credential type;
 * each cell is an expiration date, "On file" (present, no expiration), or empty.
 *
 * Maps each messy CliniConnects column header onto the clean, already-seeded canonical
 * type (see COLUMN_TO_TYPE + the discipline-routed license columns). Any column that does
 * NOT map — or any canonical type missing from the seeded taxonomy — is a hard stop: the
 * import reports it and writes nothing, rather than silently dropping or guessing.
 *
 * Idempotent: credentials are firstOrCreate'd on (provider_id, document_type_id), so a
 * re-run after a partial failure never duplicates or clobbers an existing row.
 */
class ImportCredentials extends Command
{
    protected $signature = 'sp:import-credentials {csv} {--tenant=1} {--dry-run}';

    protected $description = 'Import the 151-person CliniConnects credential export onto the canonical type taxonomy.';

    /** Non-credential columns — identity/metadata, skipped. */
    private const META_COLUMNS = [
        'First Name', 'Last Name', 'Discipline', 'Licensed State', 'Zip Code', 'Payroll ID',
    ];

    /** Columns dropped by request — not migrated, not seeded. */
    private const DROP_COLUMNS = ['DO NOT USE'];

    /** Columns routed onto the discipline-matched State License type, not a type of their own. */
    private const DISCIPLINE_LICENSE_COLUMNS = ['Professional License', 'Professional License Verification'];

    /**
     * CSV Discipline value → the State License type it routes to (assistants share the
     * parent family). This export uses PT / PTA / OT / COTA / ST — note COTA (certified
     * OT assistant → OT family) and ST (speech therapist → SLP), which differ from the
     * provider roster's own OTA / SLP codes; extra codes are kept for robustness.
     */
    private const DISCIPLINE_TO_LICENSE = [
        'PT' => 'State License (PT)',
        'PTA' => 'State License (PT)',
        'OT' => 'State License (OT)',
        'OTA' => 'State License (OT)',
        'COTA' => 'State License (OT)',
        'SLP' => 'State License (SLP)',
        'SLPA' => 'State License (SLP)',
        'ST' => 'State License (SLP)',
    ];

    /**
     * CSV credential column → canonical seeded type name. Near-duplicate CliniConnects
     * headers fold onto one canonical entry (NSO → Liability/Malpractice Insurance, the
     * HIPAA/HIPPA + Alzheimer's + Human Trafficking variants onto their single entries).
     *
     * @var array<string, string>
     */
    private const COLUMN_TO_TYPE = [
        'Activa Orientation Checklist' => 'Activa Orientation Checklist',
        'Agency Forms' => 'Agency Forms',
        "Alzheimer's 1 Hour Training Video DOEA" => "Alzheimer's Continuing Education",
        "Alzheimer's Cont. Ed. Certificate" => "Alzheimer's Continuing Education",
        'Auto Insurance' => 'Auto Insurance',
        'COVID-19 Exemption Form' => 'COVID-19 Exemption Form',
        'COVID-19 Vaccine' => 'COVID-19 Vaccine',
        'CPR/BLS' => 'CPR/BLS',
        'CPR/BLS (Back Side of Card)' => 'CPR/BLS',
        'Chest X-Ray' => 'Chest X-Ray',
        'Competency' => 'Competency',
        'Domestic Violence CEU (2 HR)' => 'Domestic Violence CEU',
        "Driver's License" => "Driver's License",
        'Elder Abuse CEU' => 'Elder Abuse CEU',
        'FCTS Hire Packet' => 'FCTS Hire Packet',
        'Flu Shot' => 'Flu Shot',
        'HIPAA / Confidentiality Training' => 'HIPAA / Confidentiality Training',
        'HIPPA CEU' => 'HIPAA / Confidentiality Training',
        'HIV/AIDS Training Certificate' => 'HIV/AIDS Training Certificate',
        'HR Memo' => 'HR Memo',
        'Hepatitis B Form' => 'Hepatitis B Form',
        'Human Trafficking CEU' => 'Human Trafficking Prevention',
        'Human Trafficking Prevention Certificate' => 'Human Trafficking Prevention',
        'Level 2 Fingerprinting AHCA Affidavit' => 'Level 2 Fingerprinting AHCA Affidavit',
        'Level 2 Fingerprinting AHCA Verification' => 'Level 2 Fingerprinting AHCA Verification',
        'Liability/Malpractice Insurance' => 'Liability/Malpractice Insurance',
        'Lymphedema Certificate' => 'Lymphedema Certificate',
        'NSO' => 'Liability/Malpractice Insurance',
        'OIG Search Results' => 'OIG Search Results',
        'OSHA / Bloodborne Pathogens CEU Certificate' => 'OSHA/Bloodborne Pathogens CEU',
        'Other Licenses' => 'Other Licenses',
        'Physical/Health Clearance' => 'Physical/Health Clearance',
        'Prevention of Medical Errors CEU Certificate' => 'Prevention of Medical Errors CEU',
        'Rate Sheet' => 'Rate Sheet',
        'Resume' => 'Resume',
        'Social Security Card' => 'Social Security Card',
        'TB Questionnaire' => 'TB Questionnaire',
        'TB Test' => 'TB Test',
        'Vehicle Registration' => 'Vehicle Registration',
        'Work Comp Exemption Document' => 'Work Comp Exemption Document',
    ];

    public function handle(): int
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

        [$header, $rows] = $this->readCsv($path);

        // Hard stop #1: every credential column must map. Report unmapped, write nothing.
        $unmapped = $this->unmappedColumns($header);
        if ($unmapped !== []) {
            $this->error('STOP — unmapped CSV columns (nothing imported):');
            foreach ($unmapped as $col) {
                $this->error("  - {$col}");
            }

            return self::FAILURE;
        }

        // Hard stop #2: every canonical target type must exist in the seeded taxonomy.
        $typeIdByName = CredentialDocumentType::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->pluck('id', 'name')
            ->all();

        $missingTypes = $this->missingCanonicalTypes($typeIdByName);
        if ($missingTypes !== []) {
            $this->error('STOP — canonical types missing from the seeded taxonomy (run the taxonomy seeder first):');
            foreach ($missingTypes as $name) {
                $this->error("  - {$name}");
            }

            return self::FAILURE;
        }

        $providersByName = $this->providersByName($tenant->id);
        $disciplineAbbrById = Discipline::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->pluck('abbreviation', 'id')
            ->all();

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $existing = 0;
        $seen = [];
        $providersWithCredential = [];
        $unmatchedPeople = [];
        $unroutedLicense = [];
        $valueWarnings = [];

        foreach ($rows as $lineNo => $row) {
            $first = trim($row['First Name'] ?? '');
            $last = trim($row['Last Name'] ?? '');
            $name = trim("{$first} {$last}");
            $provider = $providersByName[$this->nameKey($first, $last)] ?? null;

            if (! $provider instanceof Provider) {
                $unmatchedPeople[] = "line {$lineNo}: {$name}";

                continue;
            }

            // Resolve this person's State License type (CSV discipline first, provider's as fallback).
            $abbr = strtoupper(trim($row['Discipline'] ?? '')) ?: strtoupper((string) ($disciplineAbbrById[$provider->discipline_id] ?? ''));
            $licenseTypeName = self::DISCIPLINE_TO_LICENSE[$abbr] ?? null;

            foreach ($header as $column) {
                $column = trim($column);
                if (in_array($column, self::META_COLUMNS, true) || in_array($column, self::DROP_COLUMNS, true)) {
                    continue;
                }

                $raw = trim((string) ($row[$column] ?? ''));
                if ($raw === '') {
                    continue;
                }

                // Which canonical type does this column land on for this person?
                if (in_array($column, self::DISCIPLINE_LICENSE_COLUMNS, true)) {
                    if ($licenseTypeName === null) {
                        $unroutedLicense[] = "{$name}: '{$column}' (discipline '{$abbr}' has no State License type)";

                        continue;
                    }
                    $typeName = $licenseTypeName;
                } else {
                    $typeName = self::COLUMN_TO_TYPE[$column];
                }

                $typeId = $typeIdByName[$typeName];

                // Value: "On file" = present, null expiry; otherwise a date carried through as-is.
                [$expiresAt, $warning] = $this->parseValue($raw);
                if ($warning !== null) {
                    $valueWarnings[] = "{$name}: '{$column}' = \"{$raw}\" — {$warning}";
                }

                $providersWithCredential[$provider->id] = true;

                if ($dryRun) {
                    // Predict the real firstOrCreate outcome: count distinct (provider, type)
                    // pairs, so folded columns (both license columns, NSO+Liability, etc.)
                    // collapse to one row exactly as the real run will.
                    $key = $provider->id.':'.$typeId;
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;
                        $created++;
                    }

                    continue;
                }

                try {
                    $credential = ProviderCredential::firstOrCreate(
                        ['provider_id' => $provider->id, 'document_type_id' => $typeId],
                        [
                            'expires_at' => $expiresAt,
                            'status' => 'valid',
                            'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
                            'verification_source' => ProviderCredential::SOURCE_MANUAL,
                            'notes' => 'Imported from CliniConnects export.',
                        ],
                    );

                    $credential->wasRecentlyCreated ? $created++ : $existing++;
                } catch (Throwable $e) {
                    $valueWarnings[] = "{$name}: '{$column}' — write failed: ".$e->getMessage();
                }
            }
        }

        $this->report($tenant, count($rows), $created, $existing, $providersWithCredential, $unmatchedPeople, $unroutedLicense, $valueWarnings, $dryRun);

        return ($unmatchedPeople === [] && $unroutedLicense === []) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Columns present in the CSV header that are neither metadata, dropped, discipline-routed,
     * nor in the canonical column map — i.e. would be silently lost. Import stops on these.
     *
     * @param  list<string>  $header
     * @return list<string>
     */
    private function unmappedColumns(array $header): array
    {
        $unmapped = [];
        foreach ($header as $column) {
            $column = trim($column);
            if ($column === '') {
                continue;
            }
            if (in_array($column, self::META_COLUMNS, true)
                || in_array($column, self::DROP_COLUMNS, true)
                || in_array($column, self::DISCIPLINE_LICENSE_COLUMNS, true)
                || array_key_exists($column, self::COLUMN_TO_TYPE)) {
                continue;
            }
            $unmapped[] = $column;
        }

        return $unmapped;
    }

    /**
     * Canonical type names this import will target that are absent from the seeded taxonomy.
     *
     * @param  array<string, int>  $typeIdByName
     * @return list<string>
     */
    private function missingCanonicalTypes(array $typeIdByName): array
    {
        $targets = array_unique(array_merge(
            array_values(self::COLUMN_TO_TYPE),
            array_values(self::DISCIPLINE_TO_LICENSE),
        ));

        return array_values(array_filter($targets, fn (string $name): bool => ! array_key_exists($name, $typeIdByName)));
    }

    /**
     * Providers keyed by normalized name. Names that collide across two providers are
     * dropped from the map so an ambiguous match reports as unmatched rather than guessing.
     *
     * @return array<string, Provider>
     */
    private function providersByName(int $tenantId): array
    {
        $byName = [];
        $ambiguous = [];

        foreach (Provider::withoutGlobalScopes()->where('tenant_id', $tenantId)->get() as $provider) {
            $key = $this->nameKey($provider->first_name, $provider->last_name);
            if (isset($byName[$key])) {
                $ambiguous[$key] = true;
            }
            $byName[$key] = $provider;
        }

        foreach (array_keys($ambiguous) as $key) {
            unset($byName[$key]);
        }

        return $byName;
    }

    private function nameKey(?string $first, ?string $last): string
    {
        return mb_strtolower(trim((string) $first)).'|'.mb_strtolower(trim((string) $last));
    }

    /**
     * "On file" → [null, null] (present, no expiry). A date → [CarbonImmutable, null].
     * Anything else → [null, warning] (imported as present; the odd value is surfaced).
     *
     * @return array{0: ?CarbonImmutable, 1: ?string}
     */
    private function parseValue(string $raw): array
    {
        if (mb_strtolower($raw) === 'on file') {
            return [null, null];
        }

        try {
            return [CarbonImmutable::parse($raw)->startOfDay(), null];
        } catch (Throwable) {
            return [null, 'unrecognized value — imported as present with no expiry'];
        }
    }

    /**
     * @param  array<int, true>  $providersWithCredential
     * @param  list<string>  $unmatchedPeople
     * @param  list<string>  $unroutedLicense
     * @param  list<string>  $valueWarnings
     */
    private function report(Tenant $tenant, int $rowCount, int $created, int $existing, array $providersWithCredential, array $unmatchedPeople, array $unroutedLicense, array $valueWarnings, bool $dryRun): void
    {
        $rosterCount = Provider::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $withCredential = count($providersWithCredential);

        $this->line('');
        $this->info(($dryRun ? '[DRY RUN] ' : '').'CREDENTIAL IMPORT — tenant '.$tenant->name);
        $this->info('CSV rows processed: '.$rowCount);
        $this->info(($dryRun ? 'Credential rows that WOULD be created: ' : 'Credential rows created: ').$created);
        if (! $dryRun) {
            $this->info('Credential rows already present (skipped): '.$existing);
        }
        $this->info("Providers with >=1 credential on file: {$withCredential} (of {$rowCount} CSV people; {$rosterCount} in roster)");
        $this->info('Providers with ZERO credentials on file: '.max(0, $rowCount - count($unmatchedPeople) - $withCredential));

        $this->info('Unmatched CSV people (no roster provider by name): '.count($unmatchedPeople));
        foreach ($unmatchedPeople as $p) {
            $this->error("  - {$p}");
        }

        $this->info('Unrouted license entries (no State License for discipline): '.count($unroutedLicense));
        foreach ($unroutedLicense as $u) {
            $this->error("  - {$u}");
        }

        $this->info('Value warnings: '.count($valueWarnings));
        foreach ($valueWarnings as $w) {
            $this->warn("  - {$w}");
        }

        $this->info('Column mapping: all credential columns mapped cleanly (nothing dropped or guessed).');
    }

    /**
     * @return array{0: list<string>, 1: array<int, array<string, string>>}
     */
    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $head = fgetcsv($fh, 0, ',', '"', '');
        $header = array_map(fn ($h): string => trim((string) $h), $head);
        $rows = [];
        $line = 1;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $line++;
            if (count($r) === 1 && $r[0] === null) {
                continue;
            }
            $rows[$line] = array_combine($header, array_pad($r, count($header), ''));
        }
        fclose($fh);

        return [$header, $rows];
    }
}
