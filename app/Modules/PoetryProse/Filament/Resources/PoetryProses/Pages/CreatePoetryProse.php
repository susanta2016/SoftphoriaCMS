<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages;

use App\Models\User;
use App\Modules\PoetryProse\Actions\CreatePoetryProseAction;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\PoetryProseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePoetryProse extends CreateRecord
{
    protected static string $resource = PoetryProseResource::class;

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

        return app(CreatePoetryProseAction::class)->handle($data, $actor);
    }
}
