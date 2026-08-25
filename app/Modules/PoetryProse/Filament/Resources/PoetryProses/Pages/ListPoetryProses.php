<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages;

use App\Modules\PoetryProse\Filament\Resources\PoetryProses\PoetryProseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPoetryProses extends ListRecords
{
    protected static string $resource = PoetryProseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Entry'),
        ];
    }
}
