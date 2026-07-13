<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderRatingReview extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_PROMOTION = 'promotion';

    public const TYPE_DEMOTION = 'demotion';

    public const TYPE_FLAG = 'flag';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'sp_provider_rating_reviews';

    protected $fillable = [
        'tenant_id',
        'provider_id',
        'review_type',
        'current_tier_id',
        'suggested_tier_id',
        'rating_90day_avg',
        'rating_180day_avg',
        'survey_count',
        'review_period_start',
        'review_period_end',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'provider_id' => 'integer',
            'current_tier_id' => 'integer',
            'suggested_tier_id' => 'integer',
            'reviewed_by_user_id' => 'integer',
            'rating_90day_avg' => 'decimal:2',
            'rating_180day_avg' => 'decimal:2',
            'survey_count' => 'integer',
            'review_period_start' => 'date',
            'review_period_end' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function currentTier(): BelongsTo
    {
        return $this->belongsTo(ProviderTier::class, 'current_tier_id');
    }

    public function suggestedTier(): BelongsTo
    {
        return $this->belongsTo(ProviderTier::class, 'suggested_tier_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
