<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\CreateUserAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Save/Cancel are moved into the header (instead of Filament's default
 * bottom bar) for consistency with EditUser, matching the reference UI.
 *
 * The header Create button needs ->formId('form') for the same reason as
 * EditUser's Save button: Filament's getCreateFormAction() only relies on
 * native <button type="submit"> + ancestor-<form> lookup, which breaks once
 * the button moves into the page header (see EditUser's docblock for detail).
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Add User')->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(CreateUserAction::class)->handle($data, $actor);
    }
}
