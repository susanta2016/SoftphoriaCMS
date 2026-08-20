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

    /**
     * categoryIds/tagIds are pivot state, not podcasts columns — see
     * PodcastForm's docblock-less but identical reasoning to PageForm's
     * sections/seo fields.
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        $categoryIds = $data['categoryIds'] ?? [];
        $tagIds = $data['tagIds'] ?? [];
        unset($data['categoryIds'], $data['tagIds']);

        return app(CreatePodcastAction::class)->handle($data, $categoryIds, $tagIds, $actor);
    }
}
