<?php

namespace App\Filament\Dashboard\Resources\AuditEvents\Pages;

use App\Filament\Dashboard\Resources\AuditEvents\AuditEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditEvents extends ListRecords
{
    protected static string $resource = AuditEventResource::class;

    // Read-only: no create action.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
