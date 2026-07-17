<?php

namespace App\Filament\Dashboard\Resources\AuditEvents\Pages;

use App\Filament\Dashboard\Resources\AuditEvents\AuditEventResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditEvent extends ViewRecord
{
    protected static string $resource = AuditEventResource::class;

    // Read-only: no edit/delete actions.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
