<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\Pages;

use App\Models\User;
use App\Modules\PoetryProse\Actions\CreatePoetryProseCollectionAction;
use App\Modules\PoetryProse\Filament\Resources\PoetryProseCollections\PoetryProseCollectionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePoetryProseCollection extends CreateRecord
{
    protected static string $resource = PoetryProseCollectionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(CreatePoetryProseCollectionAction::class)->handle($data, $actor);
    }
}
