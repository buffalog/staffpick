<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\Contracts\BearsTenantPhi;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\IntakeRequestHistory;
use App\Models\StaffPick\Notification;
use App\Models\StaffPick\ProviderSurvey;
use App\Models\StaffPick\Subject;
use App\Models\StaffPick\Visit;
use App\Services\StaffPick\TenantContext;
use RuntimeException;
use Tests\Feature\FeatureTest;

/**
 * Forgot-guard meta-test for the read-side fail-closed. Every patient-PHI model must be marked
 * BearsTenantPhi, and a marked read with no tenant context must throw — while the same read
 * inside TenantContext::run() or via ->crossTenant() succeeds.
 */
class TenantReadGuardTest extends FeatureTest
{
    /** The 8 patient-PHI models. Provider/ProviderApplication (clinician PII) are excluded. */
    private const PHI_MODELS = [
        Subject::class,
        IntakeRequest::class,
        IntakeRequestHistory::class,
        Assignment::class,
        AssignmentOffer::class,
        ProviderSurvey::class,
        Visit::class,
        Notification::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->set(null); // no leaked context — these tests assert the no-context path
    }

    public function test_every_phi_model_is_marked(): void
    {
        foreach (self::PHI_MODELS as $model) {
            $this->assertInstanceOf(
                BearsTenantPhi::class,
                new $model,
                "{$model} handles patient PHI and must implement BearsTenantPhi.",
            );
        }
    }

    public function test_a_phi_read_with_no_context_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHI read with no tenant context');

        Subject::query()->count();
    }

    public function test_a_phi_read_inside_a_tenant_context_succeeds(): void
    {
        $tenant = $this->createTenant();

        $count = app(TenantContext::class)->run($tenant, fn (): int => Subject::query()->count());

        $this->assertIsInt($count); // no throw — scoped to the context tenant
    }

    public function test_cross_tenant_opt_in_reads_without_a_context(): void
    {
        // Explicit opt-in: no context, but ->crossTenant() drops the scope, so no throw.
        $count = Subject::query()->crossTenant()->count();

        $this->assertIsInt($count);
    }
}
