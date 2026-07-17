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

            AuditEvent::create([
                'tenant_id' => $this->resolveTenantId(),
                'user_id' => $user?->id,
                'actor_label' => $user?->email ?? ($context['actor_label'] ?? 'system'),
                'action' => $action,
                'auditable_type' => $auditable !== null ? $auditable::class : null,
                'auditable_id' => $auditable?->getKey(),
                'subject_id' => $subjectId ?? $this->resolveSubjectId($auditable),
                'ip_address' => $request?->ip(),
                'user_agent' => $request !== null ? substr((string) $request->userAgent(), 0, 512) : null,
                'context' => $context !== [] ? $context : null,
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
