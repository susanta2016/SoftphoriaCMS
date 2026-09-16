<?php

namespace App\Filament\Resources\LightPostFlags\Pages;

use App\Filament\Resources\LightPostFlags\LightPostFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListLightPostFlags extends ListRecords
{
    protected static string $resource = LightPostFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
