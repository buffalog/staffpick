<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Subject;
use App\Services\StaffPick\TenantContext;
use RuntimeException;
use Tests\Feature\FeatureTest;

/**
 * The fail-closed write guard in BelongsToTenant: a tenant-scoped PHI model refuses to be
 * created with no resolvable tenant, and creating it inside a TenantContext auto-fills the
 * tenant_id. This is the "safe by construction" bar for background writes.
 */
class TenantWriteGuardTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // No leaked context from a sibling test — this test asserts the no-tenant path.
        app(TenantContext::class)->set(null);
    }

    public function test_creating_a_phi_model_with_no_tenant_and_no_context_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refusing to create a tenant-scoped record with no tenant');

        Subject::create(['first_name' => 'Nobody', 'last_name' => 'NoTenant', 'is_active' => true]);
    }

    public function test_creating_inside_a_tenant_context_auto_fills_tenant_id(): void
    {
        $tenant = $this->createTenant();

        $subject = app(TenantContext::class)->run($tenant, fn (): Subject => Subject::create([
            'first_name' => 'Has',
            'last_name' => 'Tenant',
            'is_active' => true,
        ]));

        $this->assertSame($tenant->id, (int) $subject->tenant_id);

        // run() restored the previous (null) context on the way out.
        $this->assertNull(app(TenantContext::class)->get());
    }
}
