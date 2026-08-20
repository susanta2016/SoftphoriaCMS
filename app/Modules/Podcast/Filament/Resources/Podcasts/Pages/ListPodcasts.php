<?php

namespace App\Modules\Podcast\Filament\Resources\Podcasts\Pages;

use App\Modules\Podcast\Filament\Resources\Podcasts\PodcastResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPodcasts extends ListRecords
{
    protected static string $resource = PodcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Podcast'),
        ];
    }
}
