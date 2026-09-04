<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Concerns\LogsRecordList;
use App\Filament\Concerns\LogsRecordView;
use App\Filament\Concerns\LogsTableRecordList;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Feature\FeatureTest;

/**
 * Repo-wide guard: no surface may put patient identifiers on screen without recording that it
 * did.
 *
 * The per-surface tests prove the eleven instrumented pages work. This one proves the set is
 * complete, and stays complete: a new list, board, calendar or banner that renders a patient
 * name must either use {@see LogsRecordList} (directly or through
 * {@see LogsTableRecordList}) or be named in EXEMPT with a reason.
 *
 * HOW IT DETECTS A DISCLOSURE, and the limits of that. For each candidate surface it assembles
 * the files that produce its output (the class, its $view blade, one level of @include /
 * View::make, and for a ListRecords page the resource's Tables/*.php) and looks for a patient
 * identifier expression. It is a heuristic on source text, not a render: it can only see
 * identifiers written as `subject->last_name`-shaped access or a Subjects-resource name column.
 * A surface that reaches a patient name by some other route would slip past, which is why the
 * per-surface tests exist alongside it. What it does reliably catch is the common case: someone
 * adds `$case->subject->last_name` to a new page and ships it unaudited.
 *
 * Single-record pages (CreateRecord/EditRecord/ViewRecord) are out of scope by construction:
 * 'viewed' is {@see LogsRecordView}'s job and a different event.
 */
class PhiListAuditCoverageTest extends FeatureTest
{
    /**
     * Surfaces that match the detector but legitimately need no 'listed' event, with the reason.
     *
     * This list is the hole in the guard, so it is itself guarded below: every entry must name a
     * file that exists, must still match the detector, and must NOT already be instrumented. A
     * stale exemption therefore fails loudly instead of quietly becoming a blanket.
     *
     * @var array<string, string>
     */
    private const EXEMPT = [
        'app/Filament/Provider/Pages/ViewCase.php' => 'Single-record page. It already records its '
            .'own "viewed" event (ViewCase.php:67) via AuditLogger, which is the correct event for '
            .'opening one patient. It extends Page rather than ViewRecord, which is the only '
            .'reason the detector sees it at all.',
    ];

    /** Patient-identifier access, as it is actually written in this codebase. */
    private const PATIENT_EXPRESSION = '/subject\s*\??->\s*(first_name|last_name|full_name|date_of_birth|address|phone|email)'
        .'|subject\.(first_name|last_name|full_name|date_of_birth|address|phone|email)'
        .'|\$patient\b/i';

    /** On the Subjects resource the listed record IS the patient, so a bare name column counts. */
    private const SUBJECT_COLUMN = "/make\('(full_name|date_of_birth|first_name|last_name)'\)/";

    private const CONCERNS = ['LogsTableRecordList', 'LogsRecordList'];

    /**
     * Candidate surfaces: every page and widget in the two PHI-bearing panels, minus the
     * single-record pages.
     *
     * @return array<int, string> repo-relative paths
     */
    private function candidateSurfaces(): array
    {
        $paths = [];

        foreach (['app/Filament/Dashboard', 'app/Filament/Provider'] as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root)));

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace(base_path().'/', '', $file->getPathname());

                if (! str_contains($relative, '/Pages/') && ! str_contains($relative, '/Widgets/')) {
                    continue;
                }

                if (preg_match('/extends\s+(CreateRecord|EditRecord|ViewRecord)\b/', (string) file_get_contents($file->getPathname()))) {
                    continue;
                }

                $paths[] = $relative;
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * The files that together produce one surface's output.
     *
     * @return array<int, string>
     */
    private function sourceFilesFor(string $relative): array
    {
        $source = (string) file_get_contents(base_path($relative));
        $files = [$relative];

        foreach ($this->matchAll("/\\\$view\s*=\s*'([^']+)'/", $source) as $view) {
            $files[] = 'resources/views/'.str_replace('.', '/', $view).'.blade.php';
        }

        // A ListRecords page renders its resource's table, which lives in a sibling directory.
        if (preg_match('/extends\s+ListRecords\b/', $source)
            && preg_match('/\$resource\s*=\s*([A-Za-z0-9_]+)::class/', $source, $m)) {
            foreach ((array) glob(base_path('app/Filament/*/Resources/*/')) as $dir) {
                if (file_exists($dir.$m[1].'.php')) {
                    foreach ((array) glob($dir.'Tables/*.php') as $table) {
                        $files[] = str_replace(base_path().'/', '', $table);
                    }
                }
            }
        }

        $files = array_values(array_filter($files, fn (string $f): bool => file_exists(base_path($f))));

        // One level of blade composition: @include(...) and View::make(...).
        foreach ($files as $file) {
            $body = (string) file_get_contents(base_path($file));

            foreach (["/@include\('([^']+)'/", "/View::make\('([^']+)'/"] as $pattern) {
                foreach ($this->matchAll($pattern, $body) as $view) {
                    $path = 'resources/views/'.str_replace('.', '/', $view).'.blade.php';

                    if (file_exists(base_path($path)) && ! in_array($path, $files, true)) {
                        $files[] = $path;
                    }
                }
            }
        }

        return $files;
    }

    /** @return array<int, string> */
    private function matchAll(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches);

        return $matches[1] ?? [];
    }

    private function rendersPatientIdentifier(string $relative): bool
    {
        foreach ($this->sourceFilesFor($relative) as $file) {
            $body = (string) file_get_contents(base_path($file));

            if (preg_match(self::PATIENT_EXPRESSION, $body)) {
                return true;
            }

            if (str_contains($file, 'Subjects') && preg_match(self::SUBJECT_COLUMN, $body)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the surface actually uses the concern.
     *
     * Reflection, not a source grep. A regex for `use <Concern>` also matches the IMPORT at the
     * top of the file, so deleting the trait from the class body left this returning true and
     * the whole guard green: verified by removing `use LogsTableRecordList;` from AllCases and
     * watching this test still pass. class_uses_recursive() answers the question that actually
     * matters, and cannot be fooled by a leftover import.
     */
    private function isInstrumented(string $relative): bool
    {
        $class = str_replace(['app/', '/', '.php'], ['App\\', '\\', ''], $relative);

        if (! class_exists($class)) {
            return false;
        }

        return array_intersect(self::CONCERNS, array_map('class_basename', class_uses_recursive($class))) !== [];
    }

    public function test_every_surface_rendering_a_patient_identifier_is_audited_or_exempt(): void
    {
        $candidates = $this->candidateSurfaces();

        // Coverage guard 1: if the directory walk broke, everything below passes vacuously.
        $this->assertGreaterThan(20, count($candidates), sprintf(
            'Only %d candidate surfaces found. The scan is not reaching the panel directories, '.
            'so this guard is inspecting almost nothing.',
            count($candidates),
        ));

        $detected = array_values(array_filter($candidates, fn (string $p): bool => $this->rendersPatientIdentifier($p)));

        // Coverage guard 2: the regexes are the part most likely to rot. If they stop matching,
        // the assertion below passes while guarding nothing. Eleven are instrumented today and
        // one is exempt; the floor is deliberately below that so ordinary churn does not trip it.
        $this->assertGreaterThanOrEqual(10, count($detected), sprintf(
            'Only %d surfaces matched the patient-identifier detector, which is fewer than the '.
            "instrumented set. PATIENT_EXPRESSION no longer matches how this codebase reads a\n".
            "patient name, so this guard is inspecting nothing.\nMatched: %s",
            count($detected),
            implode(', ', $detected) ?: '(none)',
        ));

        $unaudited = array_values(array_filter(
            $detected,
            fn (string $p): bool => ! $this->isInstrumented($p) && ! array_key_exists($p, self::EXEMPT),
        ));

        $this->assertSame([], $unaudited, sprintf(
            "These surfaces render a patient identifier but record no 'listed' audit event, so a\n".
            "staff member can browse patients on them and leave no trace (45 CFR 164.312(b)):\n\n  %s\n\n".
            "Fix: `use LogsTableRecordList;` on a HasTable surface, or `use LogsRecordList;` plus a\n".
            'logListedRecords() call at the render point. If the surface genuinely discloses '.
            'nothing, add it to EXEMPT with the reason.',
            implode("\n  ", $unaudited),
        ));
    }

    public function test_the_exemption_list_is_not_stale(): void
    {
        // Coverage guard 3: an exemption that no longer describes reality is worse than none,
        // because it silently excuses whatever the file becomes next.
        foreach (self::EXEMPT as $path => $reason) {
            $this->assertFileExists(base_path($path), sprintf(
                'EXEMPT names %s, which no longer exists. Remove the entry.', $path,
            ));

            $this->assertNotEmpty(trim($reason), "EXEMPT entry {$path} has no stated reason.");

            $this->assertTrue($this->rendersPatientIdentifier($path), sprintf(
                'EXEMPT names %s, but it no longer renders a patient identifier, so the exemption '.
                'is dead weight and would silently cover a future disclosure. Remove the entry.',
                $path,
            ));

            $this->assertFalse($this->isInstrumented($path), sprintf(
                'EXEMPT names %s, but it now uses the audit concern. Remove the entry so the '.
                'guard actually checks it.',
                $path,
            ));
        }
    }

    public function test_the_instrumented_surfaces_are_the_ones_we_expect(): void
    {
        $instrumented = array_values(array_filter(
            $this->candidateSurfaces(),
            fn (string $p): bool => $this->isInstrumented($p),
        ));

        // Pinned so that removing the concern from a page fails here with a readable diff, not
        // only in whichever per-surface test happens to cover it.
        $this->assertSame([
            'app/Filament/Dashboard/Pages/Dashboard.php',
            'app/Filament/Dashboard/Pages/SchedulerBoard.php',
            'app/Filament/Dashboard/Resources/IntakeRequests/Pages/AllCases.php',
            'app/Filament/Dashboard/Resources/IntakeRequests/Pages/Cases.php',
            'app/Filament/Dashboard/Resources/IntakeRequests/Pages/CompletedCases.php',
            'app/Filament/Dashboard/Resources/IntakeRequests/Pages/ListIntakeRequests.php',
            'app/Filament/Dashboard/Resources/Subjects/Pages/ListSubjects.php',
            'app/Filament/Dashboard/Widgets/ServiceCalendarWidget.php',
            'app/Filament/Provider/Pages/MyCases.php',
            'app/Filament/Provider/Pages/ProviderHome.php',
            'app/Filament/Provider/Widgets/MyCasesCalendar.php',
        ], $instrumented);
    }
}
