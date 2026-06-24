<?php

namespace Database\Seeders;

use App\Constants\TenancyPermissionConstants;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates login credentials for FCTS stakeholders (Jon, Petros, Ed).
 * Idempotent — safe to run multiple times.
 * Assigns all four SP roles: sp_admin, sp_staff, sp_provider, sp_referrer.
 */
class FctsStakeholderSeeder extends Seeder
{
    private const TENANT_ID = 1;
    private const PASSWORD = 'StaffPick2026!';

    private array $stakeholders = [
        ['name' => 'Jon Hodel',                 'email' => 'jon@tropicalrehab.com'],
        ['name' => 'Dr. Petros Fragiskakis',    'email' => 'drpetros@firstclasstherapy.net'],
        ['name' => 'Ed Krupski',                'email' => 'ekrupski@firstclasstherapy.net'],
    ];

    public function __construct(
        private TenantPermissionService $tenantPermissionService,
    ) {}

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Runs from a migration (migrate:fresh too), where the target tenant may not
        // exist yet — e.g. fresh test DBs. No tenant → nothing to seed against; bail
        // rather than hard-fail the whole migration run. On Railway tenant 1 persists.
        $tenant = Tenant::find(self::TENANT_ID);

        if ($tenant === null) {
            return;
        }

        foreach ($this->stakeholders as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ]
            );

            // Attach to tenant if not already attached
            if (! $user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                $tenant->users()->attach($user->id);
            }

            // Assign all four SP roles (replaces any existing tenant roles)
            $this->tenantPermissionService->assignTenantUserRoles(
                $tenant,
                $user,
                TenancyPermissionConstants::SP_TENANT_ROLES,
            );

            // $this->command->info() is null when run via migration context
        }
    }
}
