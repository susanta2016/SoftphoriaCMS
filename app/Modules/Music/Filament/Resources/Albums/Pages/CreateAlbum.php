<?php

namespace App\Modules\Music\Filament\Resources\Albums\Pages;

use App\Models\User;
use App\Modules\Music\Actions\Album\CreateAlbumAction;
use App\Modules\Music\Filament\Resources\Albums\AlbumResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateAlbum extends CreateRecord
{
    protected static string $resource = AlbumResource::class;

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

        return app(CreateAlbumAction::class)->handle($data, $actor);
    }
}
