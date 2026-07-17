<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\StaffPick\Concerns\RecordsPhiAudit;
use App\Models\StaffPick\Contracts\BearsTenantPhi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outbound notification queue for provider offers and related events.
 *
 * `recipient_type`/`recipient_id` is a loose reference (recipient_type holds a
 * domain label like "provider"/"user"/"referral_source", not a model class), so
 * it is not wired as a polymorphic relation.
 */
class Notification extends Model implements BearsTenantPhi
{
    use BelongsToTenant, HasFactory, RecordsPhiAudit;

    protected $table = 'sp_notifications';

    protected $fillable = [
        'tenant_id',
        'recipient_type',
        'recipient_id',
        'channel',
        'event_type',
        'intake_request_id',
        'provider_id',
        'subject',
        'body',
        'status',
        'sent_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'recipient_id' => 'integer',
            'intake_request_id' => 'integer',
            'provider_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
