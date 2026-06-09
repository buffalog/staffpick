<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantConfig extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_tenant_configs';

    protected $fillable = [
        'tenant_id',
        'default_radius_miles',
        'feathering_miles',
        'offer_window_seconds',
        'auto_dispatch',
        'intake_person_is_assigner',
        'default_provider_is_contractor',
        'billing_terms_days',
        'week_ending_day',
        'notify_push',
        'notify_email',
        'notify_sms',
        'referral_portal_enabled',
        'show_booked_option_in_app',
    ];

    protected function casts(): array
    {
        return [
            'default_radius_miles' => 'integer',
            'feathering_miles' => 'integer',
            'offer_window_seconds' => 'integer',
            'auto_dispatch' => 'boolean',
            'intake_person_is_assigner' => 'boolean',
            'default_provider_is_contractor' => 'boolean',
            'billing_terms_days' => 'integer',
            'notify_push' => 'boolean',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'referral_portal_enabled' => 'boolean',
            'show_booked_option_in_app' => 'boolean',
        ];
    }
}
