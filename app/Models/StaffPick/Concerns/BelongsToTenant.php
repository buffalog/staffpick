<?php

namespace App\Models\StaffPick\Concerns;

use App\Models\StaffPick\Contracts\BearsTenantPhi;
use App\Models\Tenant;
use App\Services\StaffPick\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Scopes a StaffPick model to the current tenant.
 *
 * The tenant is resolved from the runtime {@see TenantContext} first (set by background
 * work via TenantContext::run()), then the active Filament panel. Web requests get the
 * Filament tenant; jobs/commands/seeders get whatever context wraps them.
 *
 * Reads outside any tenant context still run unscoped (the deliberate cross-tenant sweep —
 * fail-closed reads are H3b). WRITES fail closed: creating a tenant-scoped record with no
 * resolvable tenant and no explicit tenant_id throws, because a silent null/cross-tenant
 * write is a HIPAA risk.
 *
 * @property int $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            if ($tenant = static::currentTenant()) {
                $query->where(
                    $query->getModel()->qualifyColumn('tenant_id'),
                    $tenant->getKey(),
                );

                return;
            }

            // No tenant context. A patient-PHI model fails CLOSED — reading it unscoped would
            // silently return every tenant's rows. Wrap the read in TenantContext::run(), or
            // call ->crossTenant() to opt into a cross-tenant read explicitly. Non-PHI models
            // keep H3a's silent no-op (the deliberate cross-tenant sweep).
            if ($query->getModel() instanceof BearsTenantPhi) {
                throw new RuntimeException(static::class.': PHI read with no tenant context. Wrap the '
                    .'read in TenantContext::run(), or call ->crossTenant() to read across tenants explicitly.');
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->tenant_id) && $tenant = static::currentTenant()) {
                $model->tenant_id = $tenant->getKey();
            }

            if (empty($model->tenant_id)) {
                throw new RuntimeException(static::class.': refusing to create a tenant-scoped record '
                    .'with no tenant. Set tenant_id explicitly or wrap the write in TenantContext::run(). '
                    .'A silent null/cross-tenant write is a HIPAA risk.');
            }
        });
    }

    /**
     * The current tenant: runtime context first (background work), then the Filament
     * panel (web). Null when neither is set.
     */
    protected static function currentTenant(): ?Tenant
    {
        $context = app(TenantContext::class)->get();

        if ($context instanceof Tenant) {
            return $context;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
