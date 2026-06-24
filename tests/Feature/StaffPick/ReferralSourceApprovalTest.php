<?php

namespace Tests\Feature\StaffPick;

use App\Events\StaffPick\ReferralSourceApproved as ReferralSourceApprovedEvent;
use App\Events\StaffPick\ReferralSourceRejected as ReferralSourceRejectedEvent;
use App\Filament\Dashboard\Resources\ReferralSources\Pages\ListReferralSources;
use App\Mail\StaffPick\ReferralSourceApproved as ReferralSourceApprovedMail;
use App\Mail\StaffPick\ReferralSourceRejected as ReferralSourceRejectedMail;
use App\Models\StaffPick\ReferralSource;
use App\Models\Tenant;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class ReferralSourceApprovalTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        $user = $this->createTenantAdmin($this->tenant);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant);
    }

    private function pendingSource(array $attributes = []): ReferralSource
    {
        return ReferralSource::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'status' => ReferralSource::STATUS_PENDING,
            'email' => 'intake@pbpeds.example.com',
        ], $attributes));
    }

    public function test_approve_sets_status_active_and_dispatches_event(): void
    {
        Event::fake([ReferralSourceApprovedEvent::class]);

        $source = $this->pendingSource();

        Livewire::test(ListReferralSources::class)
            ->callAction(TestAction::make('approve')->table($source))
            ->assertHasNoErrors();

        $this->assertSame(ReferralSource::STATUS_ACTIVE, $source->fresh()->status);

        Event::assertDispatched(
            ReferralSourceApprovedEvent::class,
            fn (ReferralSourceApprovedEvent $event): bool => $event->source->is($source),
        );
    }

    public function test_reject_with_reason_sets_status_rejected_and_dispatches_event_with_reason(): void
    {
        Event::fake([ReferralSourceRejectedEvent::class]);

        $source = $this->pendingSource();

        Livewire::test(ListReferralSources::class)
            ->callAction(TestAction::make('reject')->table($source), ['reason' => 'out_of_service_area'])
            ->assertHasNoErrors();

        $this->assertSame(ReferralSource::STATUS_REJECTED, $source->fresh()->status);

        Event::assertDispatched(
            ReferralSourceRejectedEvent::class,
            fn (ReferralSourceRejectedEvent $event): bool => $event->source->is($source) && $event->reason === 'out_of_service_area',
        );
    }

    public function test_reject_requires_a_reason(): void
    {
        $source = $this->pendingSource();

        Livewire::test(ListReferralSources::class)
            ->callAction(TestAction::make('reject')->table($source), ['reason' => null])
            ->assertHasErrors(['reason' => 'required']);

        $this->assertSame(ReferralSource::STATUS_PENDING, $source->fresh()->status);
    }

    public function test_actions_are_hidden_when_status_is_not_pending(): void
    {
        $source = $this->pendingSource(['status' => ReferralSource::STATUS_ACTIVE]);

        Livewire::test(ListReferralSources::class)
            ->assertActionHidden(TestAction::make('approve')->table($source))
            ->assertActionHidden(TestAction::make('reject')->table($source));
    }

    public function test_actions_are_visible_when_status_is_pending(): void
    {
        $source = $this->pendingSource();

        Livewire::test(ListReferralSources::class)
            ->assertActionVisible(TestAction::make('approve')->table($source))
            ->assertActionVisible(TestAction::make('reject')->table($source));
    }

    public function test_approved_email_is_queued_when_the_source_has_an_email(): void
    {
        Mail::fake();

        $source = $this->pendingSource();

        Livewire::test(ListReferralSources::class)
            ->callAction(TestAction::make('approve')->table($source));

        Mail::assertQueued(ReferralSourceApprovedMail::class);
    }

    public function test_rejected_email_is_queued_when_the_source_has_an_email(): void
    {
        Mail::fake();

        $source = $this->pendingSource();

        Livewire::test(ListReferralSources::class)
            ->callAction(TestAction::make('reject')->table($source), ['reason' => 'unable_to_verify']);

        Mail::assertQueued(ReferralSourceRejectedMail::class);
    }

    public function test_rejected_email_is_skipped_when_the_source_has_no_email(): void
    {
        Mail::fake();

        $source = $this->pendingSource(['email' => null]);

        Livewire::test(ListReferralSources::class)
            ->callAction(TestAction::make('reject')->table($source), ['reason' => 'incomplete_information']);

        Mail::assertNothingQueued();
    }
}
