<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * No EditAction — a submission's own content is never admin-editable
 * (see ContactRequestResource's docblock). Status/resolution notes are
 * changed via updateAction() only.
 */
class ViewContactRequest extends ViewRecord
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContactRequestResource::updateAction(),
        ];
    }
}
