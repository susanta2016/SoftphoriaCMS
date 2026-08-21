<?php

namespace App\Modules\Commerce\Filament\Resources\Entitlements\Pages;

use App\Modules\Commerce\Filament\Resources\Entitlements\EntitlementResource;
use Filament\Resources\Pages\ListRecords;

class ListEntitlements extends ListRecords
{
    protected static string $resource = EntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
