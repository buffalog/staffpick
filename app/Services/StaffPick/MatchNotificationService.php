<?php

namespace App\Services\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Mail\StaffPick\SchedulerAlert;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Per-event staff notifications for the match cascade.
 *
 * Channels: in-app (Filament bell) + email are per staff user, gated by that user's
 * tenant_user.notification_preferences under the tenant-wide sp_tenant_configs gate.
 * Slack is tenant-level (one webhook), so it fires once per event.
 *
 * Preference shape: { "<event>": { "in_app": bool, "email": bool, "slack": bool } }.
 * A missing event or channel key means TRUE (opt-out model) — so the column being null
 * (every existing row) means all-channels-on until the toggle UI ships. "Intake staff"
 * = tenant users holding admin / sp_admin / sp_staff, falling back to all tenant users.
 */
class MatchNotificationService
{
    public const EVENT_ACCEPTED = 'match_accepted';

    public const EVENT_REJECTED = 'match_rejected';

    public const EVENT_TIMEOUT = 'timeout';

    public const EVENT_ESCALATED = 'escalated';

    public function __construct(
        private TenantPermissionService $permissions,
        private SlackNotificationService $slack,
    ) {}

    public function notify(IntakeRequest $case, string $event, string $heading, string $body): void
    {
        $tenant = Tenant::find($case->tenant_id);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $config = TenantConfig::query()->where('tenant_id', $tenant->id)->first();
        $tenantPush = (bool) ($config->notify_push ?? true);
        $tenantEmail = (bool) ($config->notify_email ?? true);
        $url = route('filament.dashboard.resources.intake-requests.view', ['tenant' => $tenant->uuid, 'record' => $case->id]);

        foreach ($this->intakeStaff($tenant) as $user) {
            $prefs = $user->pivot?->notification_preferences;

            if ($tenantPush && $this->wants($prefs, $event, 'in_app')) {
                Notification::make()
                    ->title($heading)
                    ->body($body)
                    ->actions([NotificationAction::make('view')->label(__('View case'))->url($url)->markAsRead()])
                    ->sendToDatabase($user);
            }

            if ($tenantEmail && filled($user->email) && $this->wants($prefs, $event, 'email')) {
                try {
                    Mail::to($user->email)->queue(new SchedulerAlert($heading, $body, $url));
                } catch (Throwable $e) {
                    report($e); // email is a best-effort side channel
                }
            }
        }

        // Slack is a single tenant webhook — not per-user — so it fires once.
        $this->slack->notifyCaseEvent($case, $heading, $body);
    }

    /**
     * Tenant users holding an admin/staff role, falling back to all tenant users when
     * roles aren't resolvable. Users carry their tenant_user pivot (notification_preferences).
     *
     * @return Collection<int, User>
     */
    private function intakeStaff(Tenant $tenant): Collection
    {
        $users = $tenant->users()->get();

        try {
            $staff = $users->filter(fn (User $user): bool => array_intersect(
                $this->permissions->getTenantUserRoles($tenant, $user),
                [
                    TenancyPermissionConstants::ROLE_ADMIN,
                    TenancyPermissionConstants::ROLE_SP_ADMIN,
                    TenancyPermissionConstants::ROLE_SP_STAFF,
                ],
            ) !== []);

            if ($staff->isNotEmpty()) {
                return $staff->values();
            }
        } catch (Throwable) {
            // fall through to all tenant users
        }

        return $users;
    }

    /** A missing event/channel key (or no prefs at all) means true. Tolerates a raw JSON string. */
    private function wants(mixed $prefs, string $event, string $channel): bool
    {
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true);
        }

        return data_get(is_array($prefs) ? $prefs : null, "{$event}.{$channel}", true) !== false;
    }
}
