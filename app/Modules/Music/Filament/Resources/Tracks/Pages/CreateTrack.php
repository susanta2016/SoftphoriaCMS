<?php

namespace App\Modules\Music\Filament\Resources\Tracks\Pages;

use App\Models\User;
use App\Modules\Music\Actions\Track\CreateTrackAction;
use App\Modules\Music\Filament\Resources\Tracks\TrackResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateTrack extends CreateRecord
{
    protected static string $resource = TrackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->formId('form'),
            $this->getCreateAnotherFormAction()->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(CreateTrackAction::class)->handle($data, $actor);
    }
}
