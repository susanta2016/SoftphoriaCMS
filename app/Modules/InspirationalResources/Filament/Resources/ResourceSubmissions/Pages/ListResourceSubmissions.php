<?php

namespace App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\Pages;

use App\Modules\InspirationalResources\Filament\Resources\ResourceSubmissions\ResourceSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListResourceSubmissions extends ListRecords
{
    protected static string $resource = ResourceSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
