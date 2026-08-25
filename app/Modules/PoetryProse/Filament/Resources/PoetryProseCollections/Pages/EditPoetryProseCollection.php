<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Pages;

use App\Models\User;
use App\Modules\PoetryProse\Actions\UpdatePoetryProseCollectionAction;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\PoetryProseCollectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditPoetryProseCollection extends EditRecord
{
    protected static string $resource = PoetryProseCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->requiresConfirmation(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UpdatePoetryProseCollectionAction::class)->handle($record, $data, $actor);
    }
}
