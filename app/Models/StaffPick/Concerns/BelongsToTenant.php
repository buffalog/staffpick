<?php

namespace App\Models\StaffPick\Concerns;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a StaffPick model to the current Filament tenant.
 *
 * The global scope and auto-fill only engage when a tenant is resolvable from
 * the active Filament panel. In console, queue, and seeder contexts there is no
 * current panel, so {@see currentTenant()} returns null and both behaviors
 * become no-ops — queries run unscoped and tenant_id must be set explicitly.
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
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->tenant_id) && $tenant = static::currentTenant()) {
                $model->tenant_id = $tenant->getKey();
            }
        });
    }

    /**
     * The current Filament tenant, or null outside a tenant-aware context.
     */
    protected static function currentTenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
