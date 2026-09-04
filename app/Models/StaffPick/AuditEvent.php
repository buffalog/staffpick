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
        'repeat_count',
        'last_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'auditable_id' => 'integer',
            'subject_id' => 'integer',
            // jsonb on the wire; pdo_pgsql still hands it back as a string, so the array cast
            // is unchanged by the text -> jsonb migration and keeps decoding/encoding it.
            'context' => 'array',
            'occurred_at' => 'datetime',
            'repeat_count' => 'integer',
            'last_occurred_at' => 'datetime',
        ];
    }

    /**
     * The only attributes a recorded event may ever gain, and only ever in one direction.
     *
     * @var array<int, string>
     */
    public const ACCRUABLE = ['repeat_count', 'last_occurred_at'];

    /**
     * Immutability, stated precisely rather than as a blanket.
     *
     * The property an audit record has to hold is that an event, once written, cannot be altered
     * or erased to conceal what happened. A blanket "no updates" is one way to get that, and it
     * is what this model used to do. It is not the only way, and it is not quite the property:
     * what matters is that the record can never assert LESS than it did a moment ago.
     *
     * Collapsing repeated identical renders needs repeat_count and last_occurred_at to move. Both
     * are monotonic and purely additive: the count only rises, the window only extends, and
     * neither can change WHO accessed, WHAT was disclosed, WHEN the access began, or from where.
     * A collapsed row therefore always claims at least as much disclosure as before, never less,
     * which is the direction that matters for accountability. Nothing here can be used to hide an
     * access; it can only record more of one.
     *
     * Every other attribute stays exactly as immutable as it was, and deletion stays impossible.
     * This is enforced per-field rather than trusted, so the guarantee is now finer-grained and
     * machine-checked instead of a blanket that was about to need an undocumented exception.
     *
     * For the DB-level hardening tracked as SRA R6: the same rule is expressible as a BEFORE
     * UPDATE trigger (reject unless only these two columns changed, and only upward), so this
     * does not block that work.
     */
    protected static function booted(): void
    {
        // last_occurred_at is NOT NULL and means "the most recent occurrence". A brand new event
        // has occurred exactly once, so it starts equal to occurred_at. Done here rather than at
        // the write site so it holds for every writer: AuditLogger, factories, seeders, tests.
        static::creating(function (self $event): void {
            if ($event->last_occurred_at === null) {
                $event->last_occurred_at = $event->occurred_at ?? now();
            }
        });

        static::updating(function (self $event): void {
            $illegal = array_diff(array_keys($event->getDirty()), self::ACCRUABLE);

            if ($illegal !== []) {
                throw new RuntimeException(
                    'Audit events are immutable; refused to change: '.implode(', ', $illegal).'.'
                );
            }

            if ($event->isDirty('repeat_count')
                && (int) $event->repeat_count <= (int) $event->getOriginal('repeat_count')) {
                throw new RuntimeException('Audit event repeat_count may only increase.');
            }

            if ($event->isDirty('last_occurred_at')
                && $event->last_occurred_at < $event->getOriginal('last_occurred_at')) {
                throw new RuntimeException('Audit event last_occurred_at may only advance.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit events are immutable.');
        });
    }
}
