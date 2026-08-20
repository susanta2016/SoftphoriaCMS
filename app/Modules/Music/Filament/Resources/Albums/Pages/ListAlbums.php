<?php

namespace App\Modules\Music\Filament\Resources\Albums\Pages;

use App\Modules\Music\Filament\Resources\Albums\AlbumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAlbums extends ListRecords
{
    protected static string $resource = AlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Album'),
        ];
    }
}
