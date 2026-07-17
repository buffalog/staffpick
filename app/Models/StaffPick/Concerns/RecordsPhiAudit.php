<?php

namespace App\Models\StaffPick\Concerns;

use App\Services\StaffPick\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records a HIPAA audit event on every create/update/delete of a patient-PHI model. The old and
 * new values ARE stored (the audit log is PHI by design). The AuditLogger never throws, so a
 * failed audit write can never break the underlying model save.
 *
 * Applied to the 8 BearsTenantPhi models only. NOT applied to reference data such as ZipCentroid.
 */
trait RecordsPhiAudit
{
    public static function bootRecordsPhiAudit(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->record('created', $model, ['changes' => self::auditCreatedValues($model)]);
        });

        static::updated(function (Model $model): void {
            $changes = self::auditUpdatedChanges($model);

            if ($changes === []) {
                return; // nothing meaningful changed (e.g. a touch); no event
            }

            app(AuditLogger::class)->record('updated', $model, ['changes' => $changes]);
        });

        static::deleted(function (Model $model): void {
            app(AuditLogger::class)->record('deleted', $model, []);
        });
    }

    /**
     * The new record's values, minus timestamps.
     *
     * @return array<string, mixed>
     */
    private static function auditCreatedValues(Model $model): array
    {
        $values = $model->getAttributes();

        unset($values['created_at'], $values['updated_at']);

        return $values;
    }

    /**
     * Per changed field: {old, new}, excluding updated_at.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private static function auditUpdatedChanges(Model $model): array
    {
        $changes = [];

        foreach ($model->getChanges() as $field => $new) {
            if ($field === 'updated_at') {
                continue;
            }

            $changes[$field] = ['old' => $model->getOriginal($field), 'new' => $new];
        }

        return $changes;
    }
}
