<?php

namespace App\Console\Commands;

use App\Constants\TenancyPermissionConstants;
use App\Models\Role;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Database\Seeders\TenantTaxonomySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffpick:setup-tenant
        {--email= : Admin user email (default: the bootstrap admin)}
        {--user-name= : Admin display name (default: derived from the email)}
        {--name= : Tenant name (default: the bootstrap tenant)}
        {--slug= : Tenant slug / URL identifier (default: derived from the name)}
        {--password= : Password to set when the admin user is first created}
        {--super-admin-email= : Associate a super admin with the tenant via bypass access (not as a member)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bootstrap an admin user and tenant (idempotent — safe to run repeatedly).';

    private const DEFAULT_ADMIN_NAME = 'Jeremy Pihl';

    private const DEFAULT_ADMIN_EMAIL = 'jeremy@thepihls.org';

    private const DEFAULT_TENANT_NAME = 'First Class Therapy Solutions';

    private const DEFAULT_TENANT_SLUG = 'fcts';

    public function handle(TenantPermissionService $tenantPermissionService): int
    {
        $email = $this->option('email') ?: self::DEFAULT_ADMIN_EMAIL;
        $tenantName = $this->option('name') ?: self::DEFAULT_TENANT_NAME;
        // A custom name derives its slug; the default tenant keeps its 'fcts' slug.
        $slug = $this->option('slug')
            ?: ($this->option('name') ? Str::slug($this->option('name')) : self::DEFAULT_TENANT_SLUG);
        $userName = $this->option('user-name')
            ?: ($this->option('email') ? Str::headline(Str::before($email, '@')) : self::DEFAULT_ADMIN_NAME);

        // The tenant role templates and the global admin role come from the
        // RolesAndPermissionsSeeder; bail early with a clear hint if it hasn't run.
        $globalAdminRole = Role::query()
            ->where('name', TenancyPermissionConstants::ROLE_ADMIN)
            ->where('is_tenant_role', false)
            ->first();

        if ($globalAdminRole === null) {
            $this->error("The global '".TenancyPermissionConstants::ROLE_ADMIN."' role was not found. Run `php artisan db:seed --force` first.");

            return self::FAILURE;
        }

        // 1. Admin user.
        $newPassword = null;
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            // The password cast hashes this automatically.
            $newPassword = $this->option('password') ?: Str::password(16);

            $user = User::create([
                'name' => $userName,
                'email' => $email,
                'password' => $newPassword,
                'is_admin' => true,
            ]);

            $this->info("Created admin user {$user->email}.");
        } else {
            if (! $user->is_admin) {
                $user->update(['is_admin' => true]);
            }

            $this->info("Found existing admin user {$user->email}.");
        }

        // assignRole is idempotent; assign the role object to avoid the name
        // ambiguity between the global and tenant-template 'admin' roles.
        $user->assignRole($globalAdminRole);

        // 2. Tenant — keyed on the uuid, which is the tenant's URL slug.
        $tenant = Tenant::firstOrCreate(
            ['uuid' => $slug],
            [
                'name' => $tenantName,
                'is_name_auto_generated' => false,
                'created_by' => $user->id,
            ],
        );

        $this->info($tenant->wasRecentlyCreated
            ? "Created tenant '{$tenant->name}' (/{$tenant->uuid})."
            : "Found existing tenant '{$tenant->name}' (/{$tenant->uuid}).");

        // 3. Associate the user with the tenant and grant the tenant admin role.
        $tenant->users()->syncWithoutDetaching([$user->id]);
        $tenantPermissionService->assignTenantUserRole(
            $tenant,
            $user,
            TenancyPermissionConstants::TENANT_CREATOR_ROLE,
        );

        // 4. Ensure the tenant has a config row (default entity labels + matching
        //    engine defaults come from the column defaults).
        TenantConfig::firstOrCreate(['tenant_id' => $tenant->id]);

        // 5. Seed the default StaffPick taxonomy for the tenant (idempotent).
        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);
        $this->info('Seeded default taxonomy (disciplines, tiers, credential document types, reason lists).');

        // 5b. Optionally ensure a super admin exists for the platform. They are NOT
        //     added to the tenant's members — they reach it through super-admin bypass.
        if ($superAdminEmail = $this->option('super-admin-email')) {
            $superAdmin = User::query()->where('email', $superAdminEmail)->first();

            if ($superAdmin === null) {
                $superAdmin = new User([
                    'name' => Str::headline(Str::before($superAdminEmail, '@')),
                    'email' => $superAdminEmail,
                    'is_admin' => true,
                ]);
                $superAdmin->password = Str::password(20);
                $superAdmin->forceFill(['email_verified_at' => now()]);
            }

            $superAdmin->is_admin = true;
            $superAdmin->is_super_admin = true;
            $superAdmin->save();

            $this->info("Associated super admin {$superAdmin->email} (bypass access — not a tenant member).");
        }

        // 6. Report URLs (and the password, only when one was just generated).
        $dashboardUrl = rtrim(config('app.url'), '/').'/dashboard/'.$tenant->uuid;

        $this->newLine();
        $this->info('Setup complete.');
        $this->line('  Login:     '.route('login'));
        $this->line('  Dashboard: '.$dashboardUrl);
        $this->line('  Email:     '.$user->email);

        if ($newPassword !== null) {
            $this->line('  Password:  '.$newPassword);
        } else {
            $this->comment('  Existing user — password left unchanged.');
        }

        return self::SUCCESS;
    }
}
