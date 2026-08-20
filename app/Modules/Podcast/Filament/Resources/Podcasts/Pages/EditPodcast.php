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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['categoryIds'] = $this->record->categories()->pluck('categories.id')->all();
        $data['tagIds'] = $this->record->tags()->pluck('tags.id')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        $categoryIds = $data['categoryIds'] ?? [];
        $tagIds = $data['tagIds'] ?? [];
        unset($data['categoryIds'], $data['tagIds']);

        return app(UpdatePodcastAction::class)->handle($record, $data, $categoryIds, $tagIds, $actor);
    }
}
