<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Closure;

/**
 * Runtime tenant context for background work (jobs, commands, seeders) where there is no
 * Filament panel to resolve the tenant from.
 *
 * {@see BelongsToTenant} consults this first, so wrapping a
 * unit of work in {@see run()} makes every tenant-scoped query and auto-fill inside it
 * resolve to that tenant — the same isolation web requests get from the Filament panel.
 * Registered as a singleton so set() persists across app(TenantContext::class) calls.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Run $callback with $tenant as the active context, restoring the previous context
     * afterward. Nesting- and exception-safe (the finally always restores).
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
