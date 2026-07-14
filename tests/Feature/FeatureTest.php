<?php

namespace Tests\Feature;

use App\Constants\TenancyPermissionConstants;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Database\Seeders\Testing\TestingDatabaseSeeder;
use Filament\Facades\Filament;
use Tests\TestCase;

class FeatureTest extends TestCase
{
    protected static bool $setUpHasRunOnce = false;

    /** Monotonic per-process counter behind {@see uniqueEmail()}. */
    private static int $uniqueEmailSequence = 0;

    /**
     * A test email guaranteed unique for the whole run.
     *
     * Tests used to mint these as 'existing'.rand(1, 10000).'@example.com'. The suite shares
     * ONE database that is never rolled back (see setUp below — migrate:fresh once per
     * process, no RefreshDatabase, which is a documented pdo_sqlsrv deadlock landmine), and
     * users.email is UNIQUE. Roughly a dozen tests drew from that 10,000-value space, so a
     * birthday collision blew up a random test on ~1% of runs.
     *
     * Worse, it was not stably random: Faker draws from PHP's global mt_rand, so ANY change
     * to how many tests run re-rolls every subsequent rand() — which is why simply adding
     * tests kept tripping it in unrelated files.
     *
     * The counter shares its lifetime with the database (both reset per process), so this is
     * collision-proof by construction rather than by luck.
     */
    protected function uniqueEmail(string $prefix = 'user'): string
    {
        return sprintf('%s%d@example.com', $prefix, ++static::$uniqueEmailSequence);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$setUpHasRunOnce) {
            $this->artisan('migrate:fresh');
            $this->seed(TestingDatabaseSeeder::class);

            static::$setUpHasRunOnce = true;
        }

        $this->configureDefaultCurrency();
        $this->withoutExceptionHandling();
        $this->withoutVite();
    }

    protected function createUser(?Tenant $tenant = null, array $tenantPermissions = [], array $attributes = [])
    {
        $user = User::factory()->create($attributes);

        if ($tenant !== null) {
            $tenant->users()->attach($user);

            foreach ($tenantPermissions as $permission) {
                $user->tenants()->where('tenant_id', $tenant->id)->first()->pivot->givePermissionTo($permission);
            }
        }

        return $user;
    }

    protected function createTenant()
    {
        return Tenant::factory()->create();
    }

    /**
     * Create a user who is an ADMIN of the given tenant. StaffPick PHI resources
     * (intake requests, subjects, providers, referral sources) are restricted to
     * tenant admins via StaffPickAdminPolicy, so tests exercising those resources
     * must act as an admin.
     */
    protected function createTenantAdmin(?Tenant $tenant = null, array $attributes = []): User
    {
        $tenant = $tenant ?? $this->createTenant();
        $user = $this->createUser($tenant, [], $attributes);

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        // Assign sp_admin alongside the legacy admin role. StaffPick pages/policies gate on
        // sp_admin/sp_staff via SpRoleAccess after the RBAC overhaul, so a test admin must
        // carry sp_admin to authorize the same way a real admin account does in production —
        // without it every canAccess() abort_unless(...) returns 403.
        app(TenantPermissionService::class)->assignTenantUserRoles(
            $tenant,
            $user,
            [
                TenancyPermissionConstants::ROLE_ADMIN,
                TenancyPermissionConstants::ROLE_SP_ADMIN,
            ],
        );

        return $user;
    }

    protected function createAdminUser()
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $user->each(function ($user) {
            $user->assignRole('admin');
        });

        return $user;
    }

    protected function configureDefaultCurrency(): void
    {
        config()->set('app.default_currency', 'USD');
    }
}
