<?php

namespace App\Modules\Commerce\Filament\Resources\Entitlements\Pages;

use App\Modules\Commerce\Filament\Resources\Entitlements\EntitlementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEntitlement extends ViewRecord
{
    protected static string $resource = EntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EntitlementResource::revokeAction(),
        ];
    }
}
