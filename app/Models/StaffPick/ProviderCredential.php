<?php

namespace App\Models\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inherits tenancy through its parent provider; not directly tenant-scoped.
 */
class ProviderCredential extends Model
{
    use HasFactory;

    public const VERIFICATION_UNVERIFIED = 'unverified';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_FAILED = 'failed';

    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_PENDING_MANUAL = 'pending_manual_confirmation';

    public const SOURCE_API = 'api';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_DEEP_LINK = 'deep_link';

    protected $table = 'sp_provider_credentials';

    protected $fillable = [
        'provider_id',
        'document_type_id',
        'document_number',
        'issued_at',
        'expires_at',
        'status',
        'file_path',
        'notes',
        'verified_at',
        'verified_by_user_id',
        'license_number',
        'verification_status',
        'verification_source',
        'last_verified_at',
        'verification_response',
    ];

    protected function casts(): array
    {
        return [
            'provider_id' => 'integer',
            'document_type_id' => 'integer',
            'verified_by_user_id' => 'integer',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'verification_response' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(CredentialDocumentType::class, 'document_type_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CredentialAttachment::class, 'provider_credential_id');
    }

    /**
     * The visibility gate (spec section 2): a credential row is visible if the viewer
     * may see all credentials (HR/admin/super-admin) OR its type is flagged
     * visible_to_scheduler. The Scheduler view (sp_staff) never even queries an HR-only
     * row — it is absent, not redacted. Apply on every list/report query staff can reach.
     */
    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        if (SpRoleAccess::canSeeAllCredentials()) {
            return $query;
        }

        return $query->whereHas('documentType', fn (Builder $q): Builder => $q->where('visible_to_scheduler', true));
    }

    /**
     * The single visible_to_scheduler gate governing visibility, upload, view/download, and
     * verification together for a given user. Computed from THIS record's own tenant (via
     * its provider) rather than the ambient Filament tenant, so it is correct both inside
     * the panel and on the standalone attachment-streaming route where no panel tenant is
     * resolved. super-admin passes for every type; sp_admin/sp_hr (canSeeAllCredentials)
     * pass for every type; sp_staff passes only for types flagged visible_to_scheduler.
     *
     * pdo_sqlsrv returns tenant_id as a string, so it is cast to int for the role lookup.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $tenantId = $this->provider?->tenant_id;

        if ($tenantId === null) {
            return false;
        }

        if ($user->hasAnySpRole((int) $tenantId, [
            TenancyPermissionConstants::ROLE_SP_ADMIN,
            TenancyPermissionConstants::ROLE_SP_HR,
        ])) {
            return true;
        }

        return (bool) $this->documentType?->visible_to_scheduler
            && $user->hasSpRole((int) $tenantId, TenancyPermissionConstants::ROLE_SP_STAFF);
    }

    /**
     * Whether the current user may VERIFY this credential — the write-side mirror of the
     * visibility rule (verifiable iff accessible). Delegates to {@see isAccessibleBy()} so
     * verification, upload, and view/download all share one gate.
     */
    public function isVerifiableByCurrentUser(): bool
    {
        $user = auth()->user();

        return $user !== null && $this->isAccessibleBy($user);
    }
}
