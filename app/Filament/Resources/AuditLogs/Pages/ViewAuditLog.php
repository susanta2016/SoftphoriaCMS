<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * No EditAction — an audit log entry has no admin-editable fields (see
 * AuditLogResource's docblock).
 */
class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;
}
