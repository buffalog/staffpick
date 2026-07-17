<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\Subjects\Pages\ViewSubject;
use App\Models\StaffPick\AuditEvent;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StaffPick\AuditLogger;
use App\Services\StaffPick\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\FeatureTest;

class AuditLoggingTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        app(TenantContext::class)->set($this->tenant);
    }

    /** Audit events for a specific auditable model, isolated from sibling tests on the shared DB. */
    private function eventsFor(string $type, int $id): Collection
    {
        return AuditEvent::query()
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->get();
    }

    public function test_updating_a_subject_writes_one_updated_event_with_changes(): void
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Alba']);
        $creatingEvents = $this->eventsFor(Subject::class, $subject->id)->count();

        $subject->update(['first_name' => 'Bianca']);

        $updated = $this->eventsFor(Subject::class, $subject->id)->firstWhere('action', 'updated');
        $this->assertNotNull($updated);
        $this->assertSame($subject->id, (int) $updated->subject_id);
        $this->assertSame($this->tenant->id, (int) $updated->tenant_id);
        $this->assertSame(['old' => 'Alba', 'new' => 'Bianca'], $updated->context['changes']['first_name']);
        // Exactly one update event added.
        $this->assertSame($creatingEvents + 1, $this->eventsFor(Subject::class, $subject->id)->count());
    }

    public function test_creating_and_deleting_a_subject_write_matching_events(): void
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id, 'last_name' => 'Created']);

        $created = $this->eventsFor(Subject::class, $subject->id)->firstWhere('action', 'created');
        $this->assertNotNull($created);
        $this->assertSame('Created', $created->context['changes']['last_name']);

        $id = $subject->id;
        $subject->delete();

        $deleted = $this->eventsFor(Subject::class, $id)->firstWhere('action', 'deleted');
        $this->assertNotNull($deleted);
    }

    public function test_opening_a_subject_view_page_writes_one_viewed_event(): void
    {
        $admin = $this->createTenantAdmin($this->tenant);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant);

        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id]);
        $before = $this->eventsFor(Subject::class, $subject->id)->where('action', 'viewed')->count();

        Livewire::test(ViewSubject::class, ['record' => $subject->id])->assertOk();

        $viewed = $this->eventsFor(Subject::class, $subject->id)->where('action', 'viewed');
        $this->assertSame($before + 1, $viewed->count(), 'exactly one viewed event per open');
        $this->assertSame($admin->email, $viewed->last()->actor_label);
    }

    public function test_successful_login_writes_a_login_event(): void
    {
        $user = User::factory()->create();

        Auth::login($user);

        $login = AuditEvent::query()
            ->where('action', 'login')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($login);
        $this->assertSame($user->email, $login->actor_label);
    }

    public function test_a_failed_login_writes_a_login_failed_event_with_the_attempted_email(): void
    {
        Auth::attempt(['email' => 'attacker@example.com', 'password' => 'wrong-password-xyz']);

        $failed = AuditEvent::query()
            ->where('action', 'login_failed')
            ->get()
            ->first(fn (AuditEvent $e): bool => ($e->context['email'] ?? null) === 'attacker@example.com');

        $this->assertNotNull($failed);
        $this->assertNull($failed->user_id);
    }

    public function test_audit_events_are_immutable(): void
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id]);
        $event = $this->eventsFor(Subject::class, $subject->id)->first();
        $this->assertNotNull($event);

        try {
            $event->update(['action' => 'tampered']);
            $this->fail('expected update to throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        try {
            $event->delete();
            $this->fail('expected delete to throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }

    public function test_a_failed_audit_write_is_swallowed_and_logged(): void
    {
        Log::spy();

        // Invalid UTF-8 in the context makes the model's json cast throw during save (driver
        // independent, unlike relying on DB-level truncation), forcing the audit write to fail.
        app(AuditLogger::class)->record('created', context: ['bad' => "\xB1\x31 not utf8"]);

        // No exception bubbled (we got here), and the failure was logged with NO PHI: the log
        // context is action + type + exception class only, never the changes/values.
        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
            return $message === 'audit write failed'
                && $context['action'] === 'created'
                && array_key_exists('exception', $context)
                && ! array_key_exists('changes', $context)
                && ! array_key_exists('bad', $context);
        })->once();
    }
}
