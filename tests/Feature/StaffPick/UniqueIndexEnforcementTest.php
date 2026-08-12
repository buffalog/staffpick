<?php

namespace Tests\Feature\StaffPick;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FeatureTest;

/**
 * Proves every collision-hardening unique index EXISTS and BITES.
 *
 * This exists because of a specific failure mode. Under SQL Server, sixteen of these
 * indexes were created by migrations that opened with
 *
 *     if ($this->isLocalFreeTds() || DB::connection()->getDriverName() !== 'sqlsrv') {
 *         return;
 *     }
 *
 * On any other driver that returns immediately and creates NOTHING, while the migration
 * reports SUCCESS. Nothing in the suite asserted an index existed, so the constraints could
 * silently vanish and CI would stay green — the defect ships and the first symptom is
 * duplicate data in production.
 *
 * So a migration that no-ops must turn CI red. Each case here asserts three things:
 *
 *   1. the index exists and is UNIQUE (and is partial exactly when it should be);
 *   2. a genuine duplicate is REJECTED, and the Postgres error names THIS index — without
 *      that, a collision caught by some unrelated unique constraint would pass silently;
 *   3. the rows the partial predicate is meant to PERMIT still insert — many NULL tokens,
 *      many inactive same-named taxonomy rows. Swapping a partial index for a plain one
 *      therefore fails too, in the opposite direction.
 *
 * Each case runs inside a transaction that is always rolled back: the suite shares one
 * database that is never refreshed between tests (see {@see FeatureTest::setUp()}), so
 * nothing here may outlive its own test.
 */
class UniqueIndexEnforcementTest extends FeatureTest
{
    /** Tenant id well clear of anything the seeders create. */
    private const TENANT = 987001;

    #[DataProvider('uniqueIndexProvider')]
    public function test_unique_index_exists_and_rejects_duplicates(
        string $table,
        array $base,
        array $duplicate,
        array $permitted,
        bool $partial,
        string $index,
    ): void {
        $definition = DB::selectOne(
            'SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
            [$index],
        );

        $this->assertNotNull(
            $definition,
            "Index {$index} does not exist on {$table}. Its migration reported success but created ".
            'nothing — this is the silent-skip failure mode this test exists to catch.',
        );

        $this->assertStringContainsString(
            'CREATE UNIQUE INDEX',
            $definition->indexdef,
            "Index {$index} exists but is not UNIQUE, so it enforces nothing.",
        );

        if ($partial) {
            $this->assertStringContainsString(
                ' WHERE ',
                $definition->indexdef,
                "Index {$index} is expected to be partial; a plain unique index here would reject ".
                'rows the design deliberately allows.',
            );
        } else {
            $this->assertStringNotContainsString(' WHERE ', $definition->indexdef);
        }

        DB::beginTransaction();

        try {
            DB::table($table)->insert($base);

            foreach ($permitted as $label => $overrides) {
                DB::table($table)->insert(array_merge($base, $overrides));
                $this->addToAssertionCount(1);
            }

            try {
                DB::table($table)->insert(array_merge($base, $duplicate));

                $this->fail("Index {$index} did not reject a duplicate row on {$table}.");
            } catch (QueryException $e) {
                $this->assertSame(
                    '23505',
                    $e->getCode(),
                    "Insert on {$table} failed for a reason other than a unique violation.",
                );

                $this->assertStringContainsString(
                    $index,
                    $e->getMessage(),
                    "The duplicate was rejected, but by something other than {$index}.",
                );
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array<string, array{string, array<string, mixed>, array<string, mixed>, array<string, array<string, mixed>>, bool, string}>
     */
    public static function uniqueIndexProvider(): array
    {
        $cases = [
            'tenants.domain' => [
                'tenants',
                ['uuid' => 'uie-tenant-a', 'name' => 'UIE A', 'domain' => 'uie-dup.example.com'],
                ['uuid' => 'uie-tenant-b', 'name' => 'UIE B'],
                [
                    'first domainless tenant' => ['uuid' => 'uie-tenant-c', 'domain' => null],
                    'second domainless tenant' => ['uuid' => 'uie-tenant-d', 'domain' => null],
                ],
                true,
                'tenants_domain_unique',
            ],

            'sp_referral_sources.intake_token' => [
                'sp_referral_sources',
                ['tenant_id' => self::TENANT, 'name' => 'UIE Source', 'intake_token' => 'uie-intake-token'],
                ['name' => 'UIE Other Source'],
                [
                    'first tokenless source' => ['name' => 'UIE T1', 'intake_token' => null],
                    'second tokenless source' => ['name' => 'UIE T2', 'intake_token' => null],
                ],
                true,
                'sp_referral_sources_intake_token_unique',
            ],

            'sp_intake_requests.reference_number' => [
                'sp_intake_requests',
                [
                    'tenant_id' => self::TENANT,
                    'subject_id' => 987101,
                    'reference_number' => 'R-987001',
                ],
                ['subject_id' => 987102],
                [
                    'first reference-less intake' => ['subject_id' => 987103, 'reference_number' => null],
                    'second reference-less intake' => ['subject_id' => 987104, 'reference_number' => null],
                    'same reference on a soft-deleted intake' => [
                        'subject_id' => 987105,
                        'deleted_at' => '2020-01-01 00:00:00',
                    ],
                ],
                true,
                'sp_intake_requests_reference_unique',
            ],

            'sp_assignment_offers.token' => [
                'sp_assignment_offers',
                [
                    'tenant_id' => self::TENANT,
                    'intake_request_id' => 987201,
                    'provider_id' => 987301,
                    'offer_sequence' => 1,
                    'token' => 'uie-offer-token',
                ],
                ['offer_sequence' => 2],
                [
                    'first tokenless offer' => ['offer_sequence' => 3, 'token' => null],
                    'second tokenless offer' => ['offer_sequence' => 4, 'token' => null],
                ],
                true,
                'sp_assignment_offers_token_unique',
            ],

            'sp_providers.email' => [
                'sp_providers',
                [
                    'tenant_id' => self::TENANT,
                    'first_name' => 'UIE',
                    'last_name' => 'Email',
                    'email' => 'uie-duplicate@example.com',
                ],
                ['first_name' => 'UIE Twin'],
                [
                    'first email-less provider' => ['email' => null],
                    'second email-less provider' => ['email' => null],
                    'same email on a soft-deleted provider' => ['deleted_at' => '2020-01-01 00:00:00'],
                ],
                true,
                'sp_providers_tenant_email_unique',
            ],

            'sp_providers.calendar_token' => [
                'sp_providers',
                [
                    'tenant_id' => self::TENANT,
                    'first_name' => 'UIE',
                    'last_name' => 'Calendar',
                    'calendar_token' => 'uie-calendar-token',
                ],
                ['first_name' => 'UIE Twin'],
                [
                    'first tokenless provider' => ['calendar_token' => null],
                    'second tokenless provider' => ['calendar_token' => null],
                ],
                true,
                'sp_providers_calendar_token_unique',
            ],

            // No filter on this one: both columns are NOT NULL and the table has no soft
            // deletes, so a plain unique index is correct and a partial one would be wrong.
            'sp_provider_credentials.(provider, document_type)' => [
                'sp_provider_credentials',
                ['provider_id' => 987301, 'document_type_id' => 987401],
                [],
                [],
                false,
                'sp_provider_credentials_type_unique',
            ],
        ];

        // The nine taxonomy tables share one shape: unique active name per tenant. The
        // predicate is `WHERE is_active` — bare, not `WHERE is_active = 1`, because the
        // column is a real Postgres boolean and `boolean = integer` has no operator. An
        // inactive row must never claim a name, which the permitted rows below assert.
        $taxonomies = [
            'sp_disciplines', 'sp_specialties', 'sp_credential_document_types',
            'sp_on_hold_reasons', 'sp_cancellation_reasons', 'sp_visit_types',
            'sp_provider_tiers', 'sp_insurance_types', 'sp_decline_reasons',
        ];

        foreach ($taxonomies as $table) {
            $cases["{$table}.(tenant, name)"] = [
                $table,
                ['tenant_id' => self::TENANT, 'name' => 'UIE Taxonomy', 'is_active' => true],
                [],
                [
                    'first inactive namesake' => ['is_active' => false],
                    'second inactive namesake' => ['is_active' => false],
                ],
                true,
                "{$table}_tenant_name_unique",
            ];
        }

        return $cases;
    }
}
