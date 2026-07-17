<?php

namespace App\Filament\Concerns;

use App\Services\StaffPick\AuditLogger;

/**
 * Records exactly one 'viewed' HIPAA audit event when a Filament ViewRecord/EditRecord page for a
 * PHI record is opened. mount() fires once per record open, so there is one event per view, never
 * per list render. The dashboard/provider panels have Filament::getTenant() set, so the audit row
 * is tenant-stamped by AuditLogger. Applied only to pages whose record is a PHI model.
 */
trait LogsRecordView
{
    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(AuditLogger::class)->record('viewed', $this->getRecord());
    }
}
