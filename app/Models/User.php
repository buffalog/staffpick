<?php

namespace App\Models;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ReferralSource;
use App\Notifications\Auth\QueuedVerifyEmail;
use App\Services\OrderService;
use App\Services\SubscriptionService;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laravel\Sanctum\HasApiTokens;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail, TwoFactorAuthenticatable
{
    use HasApiTokens, HasFactory, HasOneTimePasswords, HasRoles, Notifiable, TwoFactorAuthentication;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        // NOTE: is_super_admin is deliberately NOT fillable — it must only be set via
        // the staffpick:create-super-admin command, never through a mass-assigned form.
        'google_id',
        'public_name',
        'is_blocked',
        'notes',
        'phone_number',
        'phone_number_verified_at',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_number_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_seen_at' => 'datetime',
        'is_super_admin' => 'boolean',
    ];

    public function roadmapItems(): HasMany
    {
        return $this->hasMany(RoadmapItem::class);
    }

    public function roadmapItemUpvotes(): BelongsToMany
    {
        return $this->belongsToMany(RoadmapItem::class, 'roadmap_item_user_upvotes');
    }

    public function userParameters(): HasMany
    {
        return $this->hasMany(UserParameter::class);
    }

    public function stripeData(): HasMany
    {
        return $this->hasMany(UserStripeData::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptionTrials(): HasMany
    {
        return $this->hasMany(UserSubscriptionTrial::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'provider') {
            if ($this->is_super_admin) {
                return true;
            }
            $tenant = Filament::getTenant();

            return $tenant !== null && $this->hasSpRole($tenant->id, TenancyPermissionConstants::ROLE_SP_PROVIDER);
        }

        if ($panel->getId() === 'referrer') {
            if ($this->is_super_admin) {
                return true;
            }
            $tenant = Filament::getTenant();

            return $tenant !== null && $this->hasSpRole($tenant->id, TenancyPermissionConstants::ROLE_SP_REFERRER);
        }

        // The global super-admin panel is gated strictly to super admins.
        if ($panel->getId() === 'superadmin') {
            return $this->isSuperAdmin();
        }

        if ($panel->getId() == 'admin' && ! $this->is_admin) {
            return false;
        }

        return true;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function getPublicName()
    {
        return $this->public_name ?? $this->name;
    }

    public function scopeAdmin($query)
    {
        return $query->where('is_admin', true);
    }

    public function isAdmin()
    {
        return $this->is_admin;
    }

    public function isPhoneNumberVerified()
    {
        return $this->phone_number_verified_at !== null;
    }

    public function canImpersonate()
    {
        return $this->hasPermissionTo('impersonate users') && $this->isAdmin();
    }

    public function isSubscribed(?string $productSlug = null, ?Tenant $tenant = null): bool
    {
        /** @var SubscriptionService $subscriptionService */
        $subscriptionService = app(SubscriptionService::class);

        return $subscriptionService->isUserSubscribed($this, $productSlug, $tenant);
    }

    public function isTrialing(?string $productSlug = null, ?Tenant $tenant = null): bool
    {
        /** @var SubscriptionService $subscriptionService */
        $subscriptionService = app(SubscriptionService::class);

        return $subscriptionService->isUserTrialing($this, $productSlug, $tenant);
    }

    public function hasPurchased(?string $productSlug = null, ?Tenant $tenant = null): bool
    {
        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        return $orderService->hasUserOrdered($this, $productSlug, $tenant);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->using(TenantUser::class)->withPivot('id')->withTimestamps();
    }

    public function providerForTenant(int $tenantId): ?Provider
    {
        return Provider::where('tenant_id', $tenantId)
            ->where('user_id', $this->id)
            ->first();
    }

    public function referralSourceForTenant(int $tenantId): ?ReferralSource
    {
        return ReferralSource::where('tenant_id', $tenantId)
            ->where('user_id', $this->id)
            ->first();
    }

    public function hasSpRole(int $tenantId, string $role): bool
    {
        return in_array($role, $this->spRolesForTenant($tenantId), true);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnySpRole(int $tenantId, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasSpRole($tenantId, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function spRolesForTenant(int $tenantId): array
    {
        $pivot = $this->tenants()->where('tenant_id', $tenantId)->first()?->pivot;
        if ($pivot === null) {
            return [];
        }

        return $pivot->roles->pluck('name')->toArray();
    }

    public function defaultSpPanel(int $tenantId): string
    {
        $roles = $this->spRolesForTenant($tenantId);
        if (array_intersect([TenancyPermissionConstants::ROLE_SP_ADMIN, TenancyPermissionConstants::ROLE_SP_STAFF], $roles)) {
            return 'dashboard';
        }
        if (in_array(TenancyPermissionConstants::ROLE_SP_PROVIDER, $roles, true)) {
            return 'provider';
        }

        return 'referrer';
    }

    public function getTenants(Panel $panel): Collection
    {
        // Super admins can switch into any tenant; everyone else sees only the
        // tenants they are a member of.
        if ($this->isSuperAdmin()) {
            return Tenant::query()->get();
        }

        return $this->tenants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        // Super admins bypass tenant membership entirely (PART 1).
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->tenants()->whereKey($tenant)->exists();
    }

    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    public function referredBy(): HasOne
    {
        return $this->hasOne(Referral::class, 'referred_user_id');
    }

    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'referrer_user_id');
    }
}
