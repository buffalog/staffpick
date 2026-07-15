<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\StaffPick\Contracts\BearsTenantPhi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model implements BearsTenantPhi
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_visits';

    protected $fillable = [
        'assignment_id',
        'provider_id',
        'intake_request_id',
        'tenant_id',
        'visit_type_id',
        'visit_date',
        'check_in_time',
        'check_out_time',
        'duration_hours',
        'status',
        'is_billable',
        'bill_amount',
        'pay_amount',
        'emr_visit_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assignment_id' => 'integer',
            'provider_id' => 'integer',
            'intake_request_id' => 'integer',
            'tenant_id' => 'integer',
            'visit_type_id' => 'integer',
            'visit_date' => 'date',
            'duration_hours' => 'decimal:2',
            'is_billable' => 'boolean',
            'bill_amount' => 'decimal:2',
            'pay_amount' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    public function visitType(): BelongsTo
    {
        return $this->belongsTo(VisitType::class, 'visit_type_id');
    }
}
