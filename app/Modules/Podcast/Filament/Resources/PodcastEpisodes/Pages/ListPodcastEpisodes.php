<?php

namespace App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages;

use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\PodcastEpisodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPodcastEpisodes extends ListRecords
{
    protected static string $resource = PodcastEpisodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Episode'),
        ];
    }
}
