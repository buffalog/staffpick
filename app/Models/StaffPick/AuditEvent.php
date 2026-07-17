<?php

namespace App\Models\StaffPick;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * A single append-only HIPAA audit event (PHI write, PHI read, or auth event).
 *
 * Deliberately does NOT use BelongsToTenant and does NOT implement BearsTenantPhi. Two reasons:
 * the writer stamps tenant_id EXPLICITLY (often with no tenant context, e.g. a failed login), so
 * it must never hit the fail-closed tenant write guard; and cross-tenant compliance review (PR 2)
 * must be able to read across tenants without tripping the H3b read guard. Tenant confinement for
 * the viewer is handled explicitly there, not by the global scope.
 *
 * IMMUTABLE: events are never updated or deleted. Enforced at the model layer below; DB-level
 * immutability via restricted grants/triggers is a later hardening.
 */
class AuditEvent extends Model
{
    protected $table = 'sp_audit_events';

    public const UPDATED_AT = null; // no updated_at column

    protected $fillable = [
        'tenant_id',
        'user_id',
        'actor_label',
        'action',
        'auditable_type',
        'auditable_id',
        'subject_id',
        'ip_address',
        'user_agent',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'auditable_id' => 'integer',
            'subject_id' => 'integer',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Audit events are immutable.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit events are immutable.');
        });
    }
}
