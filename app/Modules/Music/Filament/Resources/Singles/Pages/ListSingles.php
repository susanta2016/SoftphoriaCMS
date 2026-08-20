<?php

namespace App\Modules\Music\Filament\Resources\Singles\Pages;

use App\Modules\Music\Filament\Resources\Singles\SingleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSingles extends ListRecords
{
    protected static string $resource = SingleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Single'),
        ];
    }
}
