<?php

namespace App\Filament\Resources\UtmLinks\Pages;

use App\Filament\Resources\UtmLinks\UtmLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUtmLinks extends ListRecords
{
    protected static string $resource = UtmLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add UTM Link'),
        ];
    }
}
