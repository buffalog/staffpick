<?php

namespace Tests;

use Database\Seeders\Testing\TestingDatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Real per-test isolation. On a persistent SQL Server database RefreshDatabase runs
     * migrate:fresh + seed exactly ONCE per process (before the first test's transaction),
     * then wraps every test in its own transaction and rolls it back on teardown — so no
     * test can see another's writes. It does NOT re-migrate per test. This replaces the old
     * shared-database-with-no-rollback setup that caused order-dependent Faker/unique
     * collisions across the suite.
     */
    use RefreshDatabase;

    /**
     * The one-time seed applied after the once-per-process migrate:fresh.
     *
     * @var class-string<Seeder>
     */
    protected $seeder = TestingDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Fail fast on a lock-wait instead of hanging forever. SQL Server holds the row locks
        // of each test's wrapping (RefreshDatabase) transaction; a nested DB::transaction()
        // savepoint in the code under test that contends for them would otherwise block
        // indefinitely on pdo_sqlsrv (it did — the whole suite hung). A bounded LOCK_TIMEOUT
        // turns that hang into a named, diagnosable failure so the run completes.
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::unprepared('SET LOCK_TIMEOUT 15000');
        }
    }
}
