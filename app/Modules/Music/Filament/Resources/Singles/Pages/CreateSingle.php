<?php

namespace App\Modules\Music\Filament\Resources\Singles\Pages;

use App\Models\User;
use App\Modules\Music\Actions\Single\CreateSingleAction;
use App\Modules\Music\Filament\Resources\Singles\SingleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateSingle extends CreateRecord
{
    protected static string $resource = SingleResource::class;

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

        return app(CreateSingleAction::class)->handle($data, $actor);
    }
}
