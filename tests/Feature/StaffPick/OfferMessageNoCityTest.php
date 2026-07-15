<?php

namespace Tests\Feature\StaffPick;

use App\Mail\StaffPick\OfferAvailable;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\MatchDispatchService;
use App\Services\StaffPick\MatchingEngine;
use App\Services\StaffPick\MatchingResult;
use App\Services\StaffPick\MatchNotificationService;
use App\Services\StaffPick\SmsService;
use App\Services\StaffPick\TenantContext;
use App\Services\StaffPick\TierResponseScorer;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\FeatureTest;

/**
 * Offer notifications reach the provider through the mail/SMS vendor (no BAA), so they must
 * carry no patient city. The in-app bell (Azure SQL) keeps it; these two vendor paths don't.
 */
class OfferMessageNoCityTest extends FeatureTest
{
    private const CITY = 'Zzyxcityville';

    private Tenant $tenant;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);

        // Rendering the offer email + dispatching the offer read the case's PHI; production
        // does so inside a tenant context (Filament request / scoped job). All fixtures here
        // belong to $this->tenant.
        app(TenantContext::class)->set($this->tenant);
    }

    private function offerWithCity(): AssignmentOffer
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id, 'city' => self::CITY]);
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'discipline_id' => $this->discipline->id]);
        $case = IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $this->discipline->id,
        ]);

        return AssignmentOffer::create([
            'tenant_id' => $this->tenant->id,
            'intake_request_id' => $case->id,
            'provider_id' => $provider->id,
            'offer_sequence' => 1,
            'status' => AssignmentOffer::STATUS_PENDING,
            'offered_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'token' => Str::random(48),
        ]);
    }

    public function test_the_offer_email_has_no_city(): void
    {
        $offer = $this->offerWithCity();

        $html = (new OfferAvailable($offer))->render();

        $this->assertStringNotContainsString(self::CITY, $html);
        $this->assertStringContainsString($offer->token, $html); // token link intact
    }

    public function test_the_offer_sms_has_no_city(): void
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id, 'city' => self::CITY]);
        $provider = Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'tier_id' => ProviderTier::create(['tenant_id' => $this->tenant->id, 'name' => 'Gold', 'priority' => 1])->id,
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
            'preferred_contact_channel' => Provider::CHANNEL_SMS,
            'phone' => '5615551234',
        ]);
        $case = IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $this->discipline->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ]);

        $captured = null;
        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')->once()->andReturnUsing(function (string $to, string $message) use (&$captured): bool {
            $captured = $message;

            return true;
        });

        $engine = Mockery::mock(MatchingEngine::class);
        $engine->shouldReceive('match')->andReturn(collect([new MatchingResult($provider, 1.0, true, false, [])]));

        $service = new MatchDispatchService($engine, new TierResponseScorer, app(MatchNotificationService::class), $sms);
        $service->offerTo($case, $provider);

        $offer = $case->assignmentOffers()->latest('id')->first();

        $this->assertStringNotContainsString(self::CITY, $captured);
        $this->assertStringContainsString($offer->token, $captured); // token url intact
    }
}
