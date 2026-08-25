<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Pages;

use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\PoetryProseCollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPoetryProseCollections extends ListRecords
{
    protected static string $resource = PoetryProseCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Collection'),
        ];
    }
}
