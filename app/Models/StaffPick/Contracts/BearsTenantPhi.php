<?php

namespace App\Models\StaffPick\Contracts;

/**
 * Marks a model whose rows are patient PHI (not clinician PII, not reference/taxonomy data).
 *
 * A marked model's tenant read scope fails CLOSED: a query with no resolvable tenant context
 * throws instead of silently reading across tenants (see BelongsToTenant). To read across
 * tenants on purpose — metrics, an infra sweep, super-admin — call ->crossTenant() explicitly.
 *
 * This is an empty marker; the behavior lives in the global scope.
 */
interface BearsTenantPhi {}
