<?php

namespace App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages;

use App\Models\User;
use App\Modules\Podcast\Actions\PodcastEpisode\CreatePodcastEpisodeAction;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\PodcastEpisodeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePodcastEpisode extends CreateRecord
{
    protected static string $resource = PodcastEpisodeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(CreatePodcastEpisodeAction::class)->handle($data, $actor);
    }
}
