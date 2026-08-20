<?php

namespace App\Modules\Podcast\Filament\Resources\Podcasts\Pages;

use App\Models\User;
use App\Modules\Podcast\Actions\Podcast\UpdatePodcastAction;
use App\Modules\Podcast\Filament\Resources\Podcasts\PodcastResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditPodcast extends EditRecord
{
    protected static string $resource = PodcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UpdatePodcastAction::class)->handle($record, $data, $actor);
    }
}
