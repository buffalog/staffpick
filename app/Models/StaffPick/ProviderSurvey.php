<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\StaffPick\Contracts\BearsTenantPhi;
use Database\Factories\StaffPick\ProviderSurveyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderSurvey extends Model implements BearsTenantPhi
{
    /** @use HasFactory<ProviderSurveyFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_BOUNCED = 'bounced';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_EMAIL = 'email';

    protected $table = 'sp_provider_surveys';

    protected $fillable = [
        'tenant_id',
        'assignment_id',
        'provider_id',
        'subject_id',
        'rating',
        'comment',
        'sent_at',
        'responded_at',
        'delivery_channel',
        'status',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'assignment_id' => 'integer',
            'provider_id' => 'integer',
            'subject_id' => 'integer',
            'rating' => 'integer',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function isResponded(): bool
    {
        return $this->status === self::STATUS_RESPONDED;
    }

    /**
     * Public URL the patient uses to submit their rating.
     */
    public function responseUrl(): string
    {
        return route('survey.show', ['token' => $this->token]);
    }
}
