<?php

namespace App\Modules\Podcast\Filament\Resources\Podcasts\Pages;

use App\Models\User;
use App\Modules\Podcast\Actions\Podcast\CreatePodcastAction;
use App\Modules\Podcast\Filament\Resources\Podcasts\PodcastResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePodcast extends CreateRecord
{
    protected static string $resource = PodcastResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(CreatePodcastAction::class)->handle($data, $actor);
    }
}
