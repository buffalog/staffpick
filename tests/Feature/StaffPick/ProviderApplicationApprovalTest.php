<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderApplication;
use App\Services\StaffPick\ProviderApplicationReviewService;
use Illuminate\Support\Str;
use Tests\Feature\FeatureTest;

class ProviderApplicationApprovalTest extends FeatureTest
{
    public function test_approving_an_application_for_an_existing_provider_email_rejects_it(): void
    {
        $tenant = $this->createTenant();
        $reviewer = $this->createTenantAdmin($tenant);

        Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'dup@example.com',
        ]);

        $application = ProviderApplication::create([
            'tenant_id' => $tenant->id,
            'application_token' => Str::random(40),
            'status' => ProviderApplication::STATUS_SUBMITTED,
            'first_name' => 'Dupe',
            'last_name' => 'Provider',
            'email' => 'dup@example.com',
        ]);

        try {
            app(ProviderApplicationReviewService::class)->approve($application, $reviewer);
            $this->fail('Expected RuntimeException for a duplicate provider email.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }

        // The pre-check runs before the DB::transaction, so the rejection persists.
        $application->refresh();
        $this->assertSame(ProviderApplication::STATUS_REJECTED, $application->status);
        $this->assertSame('Duplicate — provider with this email already exists.', $application->rejection_reason);
        $this->assertSame($reviewer->id, $application->reviewed_by);
        $this->assertSame(1, Provider::withoutGlobalScopes()->where('email', 'dup@example.com')->count());
    }
}
