<?php

namespace App\Models\StaffPick;

use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CredentialDocumentType extends Model
{
    use BelongsToTenant, HasFactory;

    public const METHOD_API = 'api';

    public const METHOD_DEEP_LINK = 'deep_link';

    public const METHOD_MANUAL = 'manual';

    protected $table = 'sp_credential_document_types';

    protected $fillable = [
        'tenant_id',
        'name',
        'is_required',
        'has_expiry',
        'expiry_warning_days',
        'deactivate_on_expiry',
        'is_active',
        'visible_to_scheduler',
        'verification_method',
        'api_discipline',
        'deep_link_url_template',
        'rapidapi_host',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'has_expiry' => 'boolean',
            'expiry_warning_days' => 'integer',
            'deactivate_on_expiry' => 'boolean',
            'is_active' => 'boolean',
            'visible_to_scheduler' => 'boolean',
        ];
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ProviderCredential::class, 'document_type_id');
    }

    /**
     * Restrict to types the current user may see. HR/admin/super-admin see all; the
     * Scheduler view (sp_staff) sees only visible_to_scheduler=true. Used for the upload
     * type dropdown and the credential report filter.
     */
    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        if (SpRoleAccess::canSeeAllCredentials()) {
            return $query;
        }

        return $query->where('visible_to_scheduler', true);
    }
}
