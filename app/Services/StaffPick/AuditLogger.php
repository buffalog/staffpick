<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\AuditEvent;
use App\Models\StaffPick\Subject;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single entry point for writing HIPAA audit events. Resolves the actor, tenant, and request
 * metadata, then writes ONE immutable row.
 *
 * Never throws into the app flow: a failed audit write logs a PHI-free line to laravel.log and is
 * swallowed, so a patient save or a page view is never broken by the audit layer. Writes are
 * synchronous and never queued (PHI must not land in a queue payload).
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, ?Model $auditable = null, array $context = [], ?int $subjectId = null): void
    {
        try {
            $user = auth()->user();
            $request = request();

            $tenantId = $this->resolveTenantId();

            // A repeated identical list render accrues onto its existing row instead of adding
            // a near-duplicate one. Returns false for everything else, so nothing but 'listed'
            // can ever take this path.
            if ($this->accrueRepeatedListing($action, $tenantId, $user?->id, $context)) {
                return;
            }

            AuditEvent::create([
                'tenant_id' => $tenantId,
                'user_id' => $user?->id,
                'actor_label' => $user?->email ?? ($context['actor_label'] ?? 'system'),
                'action' => $action,
                'auditable_type' => $auditable !== null ? $auditable::class : null,
                'auditable_id' => $auditable?->getKey(),
                'subject_id' => $subjectId ?? $this->resolveSubjectId($auditable),
                'ip_address' => $request?->ip(),
                'user_agent' => $request !== null ? substr((string) $request->userAgent(), 0, 512) : null,
                'context' => $context !== [] ? $context : null,
                // last_occurred_at is filled by AuditEvent's creating hook.
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            // NO PHI in this line: action + type + exception class only.
            Log::error('audit write failed', [
                'action' => $action,
                'auditable_type' => $auditable !== null ? $auditable::class : null,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * How long a repeated identical render keeps accruing onto the same row.
     *
     * 30 minutes. The board polls every 30 seconds, so a continuously open tab accrues
     * indefinitely, which is the case this exists for. A tab reopened after lunch, or the next
     * morning, is a genuinely new access and starts a new row: collapsing across a long gap
     * would misreport one continuous access window that never happened.
     */
    public const LISTED_COLLAPSE_WINDOW_MINUTES = 30;

    /**
     * Accrue a repeated identical list render onto its existing row.
     *
     * Returns true when the event was folded into an existing row (the caller must then not
     * insert), false when it must be recorded as a new event.
     *
     * Collapses ONLY when every one of these holds:
     *   - the action is 'listed' (no other event type is ever repeated in this sense)
     *   - same tenant, same user, same surface
     *   - the id SET is identical, compared as a set rather than by count: any change to who is
     *     on screen is a different disclosure and gets its own row
     *   - the previous occurrence is inside the window above
     *
     * @param  array<string, mixed>  $context
     */
    private function accrueRepeatedListing(string $action, ?int $tenantId, ?int $userId, array $context): bool
    {
        if ($action !== 'listed' || $userId === null) {
            return false;
        }

        $surface = $context['surface'] ?? null;
        $ids = $context['ids'] ?? null;

        if (! is_string($surface) || ! is_array($ids)) {
            return false;
        }

        $previous = AuditEvent::query()
            ->where('action', 'listed')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            // Containment against the jsonb column, served by sp_audit_events_context_gin.
            ->whereRaw('context @> ?', [json_encode(['surface' => $surface])])
            ->where('last_occurred_at', '>=', now()->subMinutes(self::LISTED_COLLAPSE_WINDOW_MINUTES))
            ->orderByDesc('occurred_at')
            ->first();

        if ($previous === null) {
            return false;
        }

        $previousIds = $previous->context['ids'] ?? null;

        if (! is_array($previousIds) || ! $this->sameIdSet($previousIds, $ids)) {
            return false;
        }

        // Both columns move forward only; AuditEvent::booted() enforces that and rejects any
        // other attribute changing, which is what keeps this consistent with an append-only log.
        $previous->forceFill([
            'repeat_count' => $previous->repeat_count + 1,
            'last_occurred_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Set equality, order- and duplicate-insensitive. Values are normalised to strings first so
     * an int id and its string form are not treated as different patients.
     *
     * @param  array<int, mixed>  $a
     * @param  array<int, mixed>  $b
     */
    private function sameIdSet(array $a, array $b): bool
    {
        $normalise = static function (array $ids): array {
            $ids = array_values(array_unique(array_map(static fn ($id): string => (string) $id, $ids)));
            sort($ids);

            return $ids;
        };

        return $normalise($a) === $normalise($b);
    }

    /**
     * Resolve the current tenant id without throwing (null is allowed): runtime context first
     * (background work), then the Filament panel (web).
     */
    private function resolveTenantId(): ?int
    {
        $contextTenant = app(TenantContext::class)->get();

        if ($contextTenant !== null) {
            return $contextTenant->getKey();
        }

        return Filament::getTenant()?->getKey();
    }

    /**
     * The patient the event pertains to: the Subject itself, or any model carrying a subject_id.
     */
    private function resolveSubjectId(?Model $auditable): ?int
    {
        if ($auditable instanceof Subject) {
            return $auditable->getKey();
        }

        $subjectId = $auditable?->getAttribute('subject_id');

        return $subjectId !== null ? (int) $subjectId : null;
    }
}
