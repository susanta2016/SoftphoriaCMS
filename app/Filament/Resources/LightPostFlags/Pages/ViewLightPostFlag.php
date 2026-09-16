<?php

namespace App\Filament\Resources\LightPostFlags\Pages;

use App\Filament\Resources\LightPostFlags\LightPostFlagResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLightPostFlag extends ViewRecord
{
    protected static string $resource = LightPostFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LightPostFlagResource::dismissAction(),
            LightPostFlagResource::deleteEntryAction(),
        ];
    }
}
