<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Filament\Facades\Filament;
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
        'entity_label_provider',
        'entity_label_subject',
        'entity_label_intake_request',
        'entity_label_discipline',
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

    /**
     * Resolve a tenant-configurable singular entity label for the current Filament
     * tenant, falling back to the given default when there is no tenant context, no
     * config row, or the stored value is blank.
     *
     * Reads through the cached Tenant->config relationship, so repeated calls within
     * a request (e.g. one per resource in the sidebar) hit the database at most once.
     */
    public static function entityLabel(string $entity, string $default): string
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return $default;
        }

        $value = $tenant->config?->{'entity_label_'.$entity};

        return filled($value) ? $value : $default;
    }
}
